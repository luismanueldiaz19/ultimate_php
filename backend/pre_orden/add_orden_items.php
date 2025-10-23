<?php
include '../conexion.php';
include '../utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Método no permitido'], 405);
}

$data = json_decode(file_get_contents("php://input"), true);

$preOrdenId = $data['pre_orden_id'] ?? null;
$items = $data['items'] ?? [];

if (!$preOrdenId || !is_array($items) || count($items) === 0) {
    json_response(["success" => false, "message" => "Datos incompletos o items vacíos"], 400);
}

pg_query($conn, "BEGIN");

try {
    foreach ($items as $index => $item) {
        $requiredFields = ['id_producto', 'precio', 'itbs', 'cant'];
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!isset($item[$field]) || trim($item[$field]) === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new Exception("Item #$index tiene campos faltantes: " . implode(', ', $missing));
        }

        $resInsert = pg_query_params($conn,
            "INSERT INTO item_pre_orden 
            (pre_orden_id,id_producto, nota_producto, precio, itbs, cant, estado_item, creado_en)
            VALUES ($1, $2, $3, $4, $5, $6, 'PENDIENTE', NOW())",
            [
                $preOrdenId,
                $item['id_producto'],
                $item['nota_producto'] ?? null,
                $item['precio'],
                $item['itbs'],
                $item['cant'],
   
            ]
        );

        if (!$resInsert) {
            throw new Exception("Error al insertar item #$index: " . pg_last_error($conn));
        }
    }

    // Recalcular totales
    $resTotales = pg_query_params($conn,
        "SELECT 
            SUM(precio / (1 + itbs / 100) * cant) AS bruto,
            SUM((precio - (precio / (1 + itbs / 100))) * cant) AS itbis
         FROM item_pre_orden
         WHERE pre_orden_id = $1",
        [$preOrdenId]
    );

    if (!$resTotales) {
        throw new Exception("Error al recalcular totales: " . pg_last_error($conn));
    }

    $totales = pg_fetch_assoc($resTotales);
    $bruto = round($totales['bruto'], 2);
    $itbis = round($totales['itbis'], 2);
    $final = $bruto + $itbis;

    $resUpdate = pg_query_params($conn,
        "UPDATE pre_orden 
         SET total_bruto = $1, total_itbis = $2, total_final = $3
         WHERE pre_orden_id = $4",
        [$bruto, $itbis, $final, $preOrdenId]
    );

    if (!$resUpdate) {
        throw new Exception("Error al actualizar totales de la factura: " . pg_last_error($conn));
    }

    pg_query($conn, "COMMIT");

    json_response([
        "success" => true,
        "message" => "Ítems agregados correctamente",
        "pre_orden_id" => $preOrdenId,
        "total_bruto" => $bruto,
        "total_itbis" => $itbis,
        "total_final" => $final
    ]);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    json_response(["success" => false, "message" => $e->getMessage()], 500);
}