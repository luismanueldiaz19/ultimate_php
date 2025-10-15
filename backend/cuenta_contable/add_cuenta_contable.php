<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Campos obligatorios
    $requiredFields = ['codigo_contable', 'nombre_contable', 'tipo_cuenta_contable'];

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
    $codigo = trim($data['codigo_contable']);
    $nombre = trim($data['nombre']);
    $nivel   = trim($data['nivel']);
    $tipo   = trim($data['tipo']);
    $padre  = trim($data['padre']);

    // Verificar duplicados
    $checkCodigo = pg_query_params($conn, "SELECT 1 FROM catalogo_cuentas WHERE codigo = $1 LIMIT 1", [$codigo]);
    if (pg_num_rows($checkCodigo) > 0) {
        json_response(["success" => false, "message" => "El código contable ya existe"], 409);
    }

    $checkNombre = pg_query_params($conn, "SELECT 1 FROM catalogo_cuentas WHERE nombre = $1 LIMIT 1", [$nombre]);
    if (pg_num_rows($checkNombre) > 0) {
        json_response(["success" => false, "message" => "El nombre contable ya existe"], 409);
    }

    // Insertar en DB
    $sql = "INSERT INTO public.catalogo_cuentas( codigo, nombre, nivel, tipo, padre)
            VALUES ($1, $2, $3, $4, $5) RETURNING codigo, nombre, nivel, tipo, padre";

    $params = [$codigo, $nombre, $nivel, $tipo, $padre];
    
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
