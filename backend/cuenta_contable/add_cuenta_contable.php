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

    // Valores con trim
    $codigo = trim($data['codigo_contable']);
    $nombre = trim($data['nombre_contable']);
    $tipo   = trim($data['tipo_cuenta_contable']);
    $statu  = isset($data['statu_contable']) ? ($data['statu_contable'] ? 't' : 'f') : 't';

    // Verificar duplicados
    $checkCodigo = pg_query_params($conn, "SELECT 1 FROM cuentas_contables WHERE codigo_contable = $1 LIMIT 1", [$codigo]);
    if (pg_num_rows($checkCodigo) > 0) {
        json_response(["success" => false, "message" => "El código contable ya existe"], 409);
    }

    $checkNombre = pg_query_params($conn, "SELECT 1 FROM cuentas_contables WHERE nombre_contable = $1 LIMIT 1", [$nombre]);
    if (pg_num_rows($checkNombre) > 0) {
        json_response(["success" => false, "message" => "El nombre contable ya existe"], 409);
    }

    // Insertar en DB
    $sql = "INSERT INTO public.cuentas_contables (codigo_contable, nombre_contable, tipo_cuenta_contable, statu_contable)
            VALUES ($1, $2, $3, $4)
            RETURNING id_cuenta, codigo_contable, nombre_contable, tipo_cuenta_contable, statu_contable, creado_en_contable";

    $params = [$codigo, $nombre, $tipo, $statu];
    $result = @pg_query_params($conn, $sql, $params);

    if (!$result) {
        $error = pg_last_error($conn);
        if (strpos($error, 'cuentas_contables_codigo_contable_key') !== false) {
            json_response(["success" => false, "message" => "El código contable ya existe"], 409);
        }
        json_response(["success" => false, "message" => "Error al crear cuenta contable: " . $error], 500);
    }

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
