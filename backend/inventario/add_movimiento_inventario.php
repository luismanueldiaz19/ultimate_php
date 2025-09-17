<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Validar campos obligatorios
    $requiredFields = ['tipo_movimiento_id', 'productos_lote_id', 'cantidad', 'usuario'];
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

    // Sanitizar valores
    $tipo_movimiento_id = (int) $data['tipo_movimiento_id'];
    $productos_lote_id = (int) $data['productos_lote_id'];
    $cantidad = (float) $data['cantidad'];
    $usuario = trim($data['usuario']);
    $motivo = isset($data['motivo']) ? trim($data['motivo']) : 'N/A';
    $origen_almacen_id = isset($data['origen_almacen_id']) ? (int) $data['origen_almacen_id'] : null;

    // Verificar existencia del lote
    $checkLote = pg_query_params($conn,
        "SELECT cantidad FROM productos_lotes WHERE productos_lote_id = $1",
        [$productos_lote_id]
    );

    if (pg_num_rows($checkLote) === 0) {
        json_response(["success" => false, "message" => "El lote no existe"], 404);
    }

    $lote = pg_fetch_assoc($checkLote);
    $stockActual = (float) $lote['cantidad'];

    // Validar tipo de movimiento
    $checkTipo = pg_query_params($conn,
        "SELECT tipo_movimiento FROM tipo_movimiento WHERE tipo_movimiento_id = $1",
        [$tipo_movimiento_id]
    );

    if (pg_num_rows($checkTipo) === 0) {
        json_response(["success" => false, "message" => "Tipo de movimiento inválido"], 400);
    }

    $tipo = strtolower(pg_fetch_result($checkTipo, 0, 'tipo_movimiento'));

    // Validar stock suficiente si es salida o daño
    if (in_array($tipo, ['salida', 'daño']) && $cantidad > $stockActual) {
        json_response(["success" => false, "message" => "Stock insuficiente para el movimiento"], 409);
    }

    // Insertar movimiento
    $sql = "INSERT INTO movimientos_inventario (
                tipo_movimiento_id, cantidad, motivo, origen_almacen_id, usuario, productos_lote_id
            ) VALUES ($1, $2, $3, $4, $5, $6)
            RETURNING movimientos_inventario_id";

    $params = [$tipo_movimiento_id, $cantidad, $motivo, $origen_almacen_id, $usuario, $productos_lote_id];
    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al registrar movimiento: " . $error], 500);
    }

    // Actualizar stock en productos_lotes
    if ($tipo === 'entrada' || $tipo === 'ajuste') {
        $update = "UPDATE productos_lotes SET cantidad = cantidad + $1, actualizado_en = now() WHERE productos_lote_id = $2";
    } elseif ($tipo === 'salida' || $tipo === 'daño') {
        $update = "UPDATE productos_lotes SET cantidad = cantidad - $1, actualizado_en = now() WHERE productos_lote_id = $2";
    } else {
        $update = null;
    }

    if ($update) {
        $updateResult = pg_query_params($conn, $update, [$cantidad, $productos_lote_id]);
        if (!$updateResult) {
            json_response(["success" => false, "message" => "Movimiento registrado pero no se actualizó el stock"], 500);
        }
    }

    $newId = pg_fetch_result($result, 0, 'movimientos_inventario_id');
    json_response([
        "success" => true,
        "message" => "Movimiento registrado exitosamente",
        "movimiento_id" => $newId
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>