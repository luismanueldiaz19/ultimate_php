<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Campos obligatorios
    //INSERT INTO public.catalogo_cuentas(codigo, nombre, nivel, padre, tipo_cuenta_id)
    $requiredFields = ['codigo', 'nombre', 'nivel', 'padre', 'tipo_cuenta_id'];

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
        ], 400);
    }
   //codigo, nombre, nivel, tipo, padre
    // Valores con trim
    $codigo = trim($data['codigo']);
    $nombre = trim($data['nombre']);
    $nivel   = trim($data['nivel']);
    $padre  = trim($data['padre']);
    $tipo_cuenta_id  = trim($data['tipo_cuenta_id']);

    // Verificar duplicados
    $checkCodigo = pg_query_params($conn, "SELECT 1 FROM catalogo_cuentas WHERE codigo = $1 LIMIT 1", [$codigo]);
    if (pg_num_rows($checkCodigo) > 0) {
        json_response(["success" => false, "message" => "El código contable ya existe"], 409);
        exit;
    }

    $checkNombre = pg_query_params($conn, "SELECT 1 FROM catalogo_cuentas WHERE nombre = $1 LIMIT 1", [$nombre]);
    if (pg_num_rows($checkNombre) > 0) {
        json_response(["success" => false, "message" => "El nombre contable ya existe"], 409);   
        exit;
    }

    // Insertar en DB
    $sql = "INSERT INTO public.catalogo_cuentas(codigo, nombre, nivel, padre, tipo_cuenta_id)
            VALUES ($1, $2, $3, $4, $5) RETURNING codigo, nombre, nivel,padre, tipo_cuenta_id";

    $params = [$codigo, $nombre, $nivel, $padre, $tipo_cuenta_id];
    
    $result = @pg_query_params($conn, $sql, $params);

    $newCuenta = pg_fetch_assoc($result);
    json_response([
        "success" => true,
        "message" => "Cuenta contable creada exitosamente",
        "cuenta" => $newCuenta
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
