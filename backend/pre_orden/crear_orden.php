<?php

include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Método no permitido'], 405);
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$usuarioId = $data['id_usuario'] ?? null;

if (!$usuarioId) {
    json_response(['success' => false, 'message' => 'Usuario no autenticado'], 401);
}

$requiredFields = ['ficha_id', 'id_usuario', 'id_cliente', 'fecha_entrega' ,'name_logo'];

$missingFields = [];

foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === '') {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    json_response([
        'success'   => false,
        'message'   => 'Faltan campos obligatorios',
        'faltantes' => $missingFields
    ], 400);
}

if (!isset($data['item_orden']) || !is_array($data['item_orden']) || count($data['item_orden']) === 0) {
    json_response(['success' => false, 'message' => 'Datos incompletos de pre-orden sus items no tiene'], 400);
}

pg_query($conn, 'BEGIN');

try {
    // PASO 1: Generar num_orden con la secuencia
    $resOrden = pg_query($conn, "SELECT 'ORD-' || LPAD(nextval('orden_numero_seq')::text, 9, '0') AS numero_orden");

    if (!$resOrden) {
        throw new Exception('Error al generar número de orden: ' . pg_last_error($conn));
    }
    $rowOrden = pg_fetch_assoc($resOrden);
    $numOrden = $rowOrden['numero_orden'];

    // PASO 2: Calcular totales
    // PASO 2: Calcular totales (precio ya incluye ITBIS)
    $total_bruto = 0;
    $total_itbis = 0;

    foreach ($data['item_orden'] as $item) {
        $precio_neto = $item['precio'] / (1 + ($item['itbs'] / 100));
        $linea_bruto = $precio_neto                     * $item['cant'];
        $linea_itbis = ($item['precio'] - $precio_neto) * $item['cant'];
        $total_bruto += $linea_bruto;
        $total_itbis += $linea_itbis;
    }

    $total_final = $total_bruto + $total_itbis;

    // PASO 3: Insertar pre_orden
    $sqlPreOrden = "INSERT INTO pre_orden 
    (ficha_id, num_orden, id_usuario, id_cliente, estado_hoja, estado_general, creado_en, fecha_entrega, 
     total_bruto, total_itbis, total_final, is_facturado, name_logo)
    VALUES ($1, $2, $3, $4, $10, 'PENDIENTE', NOW(), $5, $6, $7, $8, FALSE, $9)
    RETURNING pre_orden_id";

    $resPreOrden = pg_query_params($conn, $sqlPreOrden, [
        $data['ficha_id'] ?? null,
        $numOrden,
        $usuarioId,
        $data['id_cliente'],
        $data['fecha_entrega'] ?? null,
        $total_bruto,
        $total_itbis,
        $total_final,
        $data['name_logo'] ?? null,
        $data['estado_hoja'],
    ]);

    if (!$resPreOrden) {
        throw new Exception('Error al insertar pre_orden: ' . pg_last_error($conn));
    }

    $preOrdenId = pg_fetch_result($resPreOrden, 0, 'pre_orden_id');

    // PASO 4: Insertar items de la pre_orden
    foreach ($data['item_orden'] as $item) {
        $resDetalle = pg_query_params(
            $conn,
            "INSERT INTO item_pre_orden 
            (pre_orden_id, id_producto, nota_producto, precio, itbs, cant, estado_item, creado_en,design_tipo_id)
            VALUES ($1, $2, $3, $4, $5, $6, 'PENDIENTE', NOW(), $7)",
            [
                $preOrdenId,
                $item['id_producto'],
                $item['nota_producto'] ?? null,
                $item['precio'],
                $item['itbs'],
                $item['cant'],
                $item['design_tipo_id'] ?? null
            ]
        );

        if (!$resDetalle) {
            throw new Exception('Error al insertar item_pre_orden: ' . pg_last_error($conn));
        }
    }

    pg_query($conn, 'COMMIT');

    json_response([
        'success'      => true,
        'message'      => 'Pre-orden registrada exitosamente',
        'pre_orden_id' => $preOrdenId,
        'num_orden'    => $numOrden,
        'total_bruto'  => $total_bruto,
        'total_itbis'  => $total_itbis,
        'total_final'  => $total_final
    ]);

} catch (Exception $e) {
    pg_query($conn, 'ROLLBACK');
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
