<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Validar campos obligatorios
    $requiredFields = ['type_work_id', 'nombre_campo'];
    $missingFields = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        json_response([
            "success" => false,
            "message" => "Faltan campos obligatorios",
            "faltantes" => $missingFields
        ]);
        exit;
    }

    // Normalizar valores
    $type_work_id  = $data['type_work_id'];
    $nombre_campo = strtoupper(trim($data['nombre_campo']));


    // Verificar si ya existe el campo
    $checkSql = "SELECT campos_type_work_id 
                 FROM public.campos_type_work 
                 WHERE type_work_id = $1 AND UPPER(BTRIM(nombre_campo)) = $2";
    $checkParams = [$type_work_id, $nombre_campo];
    $checkResult = @pg_query_params($conn, $checkSql, $checkParams);

    if (!$checkResult) {
        json_response([
            "success" => false,
            "message" => "Error al verificar existencia: " . pg_last_error($conn)
        ]);
        exit;
    }

    if (pg_num_rows($checkResult) > 0) {
        json_response([
            "success" => false,
            "message" => "Ya existe un campo con ese nombre para este tipo de trabajo"
        ]);
        exit;
    }

    // Insertar nuevo campo
    $insertSql = "INSERT INTO public.campos_type_work(type_work_id, nombre_campo) VALUES ($1, $2) RETURNING campos_type_work_id";
    $insertParams = [$type_work_id, $nombre_campo];
    $insertResult = @pg_query_params($conn, $insertSql, $insertParams);

    if (!$insertResult) {
        json_response([
            "success" => false,
            "message" => "Error al insertar campo: " . pg_last_error($conn)
        ]);
        exit;
    }

    $campo_id = pg_fetch_result($insertResult, 0, 'campos_type_work_id');

    json_response([
        "success" => true,
        "message" => "Campo creado exitosamente",
        "campos_type_work_id" => $campo_id,
        "type_work_id" => $type_work_id,
        "nombre_campo" => $nombre_campo
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage()
    ]);
}
?>