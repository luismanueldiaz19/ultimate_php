<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Validar campos obligatorios
    $requiredFields = ['type_work', 'department_id', 'campos'];
    $missingFields = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || 
            (is_string($data[$field]) && trim($data[$field]) === '') || 
            ($field === 'campos' && !is_array($data[$field]))) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        json_response([
            "success" => false,
            "message" => "Faltan campos obligatorios",
            "faltantes" => $missingFields
        ]);
    }

    // Normalizar valores
    $type_work     = strtoupper(trim($data['type_work']));
    $department_id = $data['department_id'];
    $image_path    = 'N/A';
    $campos        = $data['campos'];

    // Verificar si ya existe el registro
    $checkSql = "SELECT type_work_id FROM public.type_work WHERE type_work = $1 AND department_id = $2";
    $checkParams = [$type_work, $department_id];
    $checkResult = @pg_query_params($conn, $checkSql, $checkParams);

    if (!$checkResult) {
        json_response([
        "success" => false,
        "message" => "Error al verificar existencia: " . pg_last_error($conn)]);   
    }

    if (pg_num_rows($checkResult) > 0) {
        $existing_id = pg_fetch_result($checkResult, 0, 'type_work_id');
        json_response([
            "success" => false,
            "message" => "Ya existe un tipo de trabajo con ese nombre en el departamento",
            "type_work_id" => $existing_id
        ]);
    }

    // Insertar nuevo type_work
    $insertTypeSql = "INSERT INTO public.type_work(type_work, department_id, image_path) VALUES ($1, $2, $3) RETURNING type_work_id";
    $insertTypeParams = [$type_work, $department_id, $image_path];
    $insertTypeResult = @pg_query_params($conn, $insertTypeSql, $insertTypeParams);

    if (!$insertTypeResult) {
        json_response([
        "success" => false,
        "message" => "Error al insertar type_work : " . pg_last_error($conn)]);
    
    }

    $type_work_id = pg_fetch_result($insertTypeResult, 0, 'type_work_id');

    // Insertar campos relacionados
    $insertCampoSql = "INSERT INTO public.campos_type_work(type_work_id, nombre_campo) VALUES ($1, $2)";
    
    foreach ($campos as $campo) {
        if (!isset($campo['nombre_campo']) || trim($campo['nombre_campo']) === '') continue;

        $nombre_campo = strtoupper(trim($campo['nombre_campo']));
        $insertCampoParams = [$type_work_id, $nombre_campo];
        $insertCampoResult = @pg_query_params($conn, $insertCampoSql, $insertCampoParams);

        if (!$insertCampoResult) {
             json_response([
              "success" => false,    
              "message" => "Error al insertar campo '{$nombre_campo}': " . pg_last_error($conn)]);}
    }

    json_response([
        "success" => true,
        "message" => "Tipo de trabajo y campos creados exitosamente",
        "type_work_id" => $type_work_id
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage()
    ]);
}