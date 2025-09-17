<?php 
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Validar campos obligatorios
    $requiredFields = ['id_producto', 'id_almacen', 'fecha_vencimiento', 'cantidad', 'usuario', 'tipo_movimiento_id'];
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
    $id_producto = (int) $data['id_producto'];
    $id_almacen = (int) $data['id_almacen'];
    $fecha_vencimiento = $data['fecha_vencimiento'];
    $cantidad = (float) $data['cantidad'];
    $usuario = trim($data['usuario']);
    $tipo_movimiento_id = (int) $data['tipo_movimiento_id'];
    $motivo = isset($data['motivo']) ? trim($data['motivo']) : 'Entrada inicial';

    // Verificar si ya existe un lote igual (opcional)
    $check = pg_query_params($conn,
        "SELECT productos_lote_id FROM productos_lotes WHERE id_producto = $1 AND id_almacen = $2 AND fecha_vencimiento = $3",
        [$id_producto, $id_almacen, $fecha_vencimiento]
    );

    if (pg_num_rows($check) > 0) {
        json_response(["success" => false, "message" => "Ya existe un lote con esos datos"], 409);
    }

    // Crear lote
    $sqlLote = "INSERT INTO productos_lotes (
        id_producto, id_almacen, fecha_vencimiento, cantidad
    ) VALUES ($1, $2, $3, $4)
    RETURNING productos_lote_id";

    $paramsLote = [$id_producto, $id_almacen, $fecha_vencimiento, $cantidad];
    $resultLote = pg_query_params($conn, $sqlLote, $paramsLote);

    if (!$resultLote) {
        json_response(["success" => false, "message" => "Error al crear lote"], 500);
    }

    $productos_lote_id = pg_fetch_result($resultLote, 0, 'productos_lote_id');

    // Registrar movimiento
    $sqlMov = "INSERT INTO movimientos_inventario (
        tipo_movimiento_id, cantidad, motivo, origen_almacen_id, usuario, productos_lote_id
    ) VALUES ($1, $2, $3, $4, $5, $6)
    RETURNING movimientos_inventario_id";

    $paramsMov = [$tipo_movimiento_id, $cantidad, $motivo, $id_almacen, $usuario, $productos_lote_id];
    $resultMov = pg_query_params($conn, $sqlMov, $paramsMov);

    if (!$resultMov) {
        json_response(["success" => false, "message" => "Error al registrar movimiento"], 500);
    }

    $movimiento_id = pg_fetch_result($resultMov, 0, 'movimientos_inventario_id');

    json_response([
        "success" => true,
        "message" => "Entrada registrada exitosamente",
        "productos_lote_id" => $productos_lote_id,
        "movimientos_inventario_id" => $movimiento_id
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>