<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Validar campos obligatorios
    $requiredFields = ['materiales_gastable_id', 'tipo', 'cantidad'];
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

    // Sanitizar y preparar datos
    $material_id = (int) $data['materiales_gastable_id'];
    $tipo = strtolower(trim($data['tipo']));
    $cantidad = (float) $data['cantidad'];
    $compra_id = isset($data['compra_id']) ? (int) $data['compra_id'] : null;
    $referencia = trim($data['referencia'] ?? '');

    if (!in_array($tipo, ['entrada', 'salida'])) {
        json_response(["success" => false, "message" => "Tipo de movimiento inválido (debe ser 'entrada' o 'salida')"], 400);
    }

    // Validar existencia del material
    $check = pg_query_params($conn, "SELECT stock_actual FROM materiales_gastable WHERE materiales_gastable_id = $1", [$material_id]);
    if (pg_num_rows($check) === 0) {
        json_response(["success" => false, "message" => "El material no existe"], 404);
    }

    $stock_actual = (float) pg_fetch_result($check, 0, 'stock_actual');

    // Validar stock suficiente si es salida
    if ($tipo === 'salida' && $stock_actual < $cantidad) {
        json_response(["success" => false, "message" => "Stock insuficiente para salida"], 409);
    }

    // Registrar movimiento y actualizar inventario
    pg_query($conn, "BEGIN");

    $sql = "INSERT INTO movimientos (materiales_gastable_id, compra_id, tipo, cantidad, referencia)
            VALUES ($1, $2, $3, $4, $5)
            RETURNING id, materiales_gastable_id, tipo, cantidad, fecha, referencia";

    $params = [$material_id, $compra_id, $tipo, $cantidad, $referencia];
    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        pg_query($conn, "ROLLBACK");
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al registrar movimiento: " . $error], 500);
    }

    // Actualizar stock
    $ajuste = ($tipo === 'entrada') ? $cantidad : -$cantidad;
    $update = pg_query_params($conn,
        "UPDATE materiales_gastable SET stock_actual = stock_actual + $1 WHERE materiales_gastable_id = $2",
        [$ajuste, $material_id]
    );

    if (!$update) {
        pg_query($conn, "ROLLBACK");
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al actualizar inventario: " . $error], 500);
    }

    pg_query($conn, "COMMIT");

    $movimiento = pg_fetch_assoc($result);
    json_response([
        "success" => true,
        "message" => "Movimiento registrado exitosamente",
        "movimiento" => $movimiento
    ]);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>