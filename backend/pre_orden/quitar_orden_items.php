<?php
include '../conexion.php';
include '../utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Método no permitido'], 405);
}

$data = json_decode(file_get_contents("php://input"), true);

$preOrdenId = $data['pre_orden_id'] ?? null;
$itemId = $data['item_pre_orden_id'] ?? null;

if (!$preOrdenId || !$itemId) {
    json_response(["success" => false, "message" => "Datos incompletos"], 400);
}

pg_query($conn, "BEGIN");

try {
    // Validar que el ítem pertenezca a la orden
    $resCheck = @pg_query_params($conn,
        "SELECT 1 FROM item_pre_orden WHERE item_pre_orden_id = $1 AND pre_orden_id = $2",
        [$itemId, $preOrdenId]
    );

    if (pg_num_rows($resCheck) === 0) {
        throw new Exception("El ítem no pertenece a la orden especificada.");
    }

    // Verificar si el ítem está referenciado en otra tabla
    $resVinculo = @pg_query_params($conn,
        "SELECT 1 FROM planificacion_work WHERE item_pre_orden_id = $1",
        [$itemId]
    );

    if (pg_num_rows($resVinculo) > 0) {
        throw new Exception("El ítem está vinculado a un proceso de planificación y no puede eliminarse.");
    }

    // Eliminar el ítem
    $resDelete = @pg_query_params($conn,
        "DELETE FROM item_pre_orden WHERE item_pre_orden_id = $1",
        [$itemId]
    );

    if (!$resDelete) {
        throw new Exception("Error al eliminar el ítem: " . pg_last_error($conn));
    }

    // Recalcular totales
    $resTotales = @pg_query_params($conn,
        "SELECT 
            COALESCE(SUM(precio / (1 + itbs / 100) * cant), 0) AS bruto,
            COALESCE(SUM((precio - (precio / (1 + itbs / 100))) * cant), 0) AS itbis
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

    // Actualizar la orden
    $resUpdate = @pg_query_params($conn,
        "UPDATE pre_orden 
         SET total_bruto = $1, total_itbis = $2, total_final = $3
         WHERE pre_orden_id = $4",
        [$bruto, $itbis, $final, $preOrdenId]
    );

    if (!$resUpdate) {
        throw new Exception("Error al actualizar totales: " . pg_last_error($conn));
    }

    pg_query($conn, "COMMIT");

    json_response([
        "success" => true,
        "message" => "Ítem eliminado correctamente",
        "pre_orden_id" => $preOrdenId,
        "total_bruto" => $bruto,
        "total_itbis" => $itbis,
        "total_final" => $final
    ]);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");

    $errorMsg = $e->getMessage();

    if (strpos($errorMsg, 'violates foreign key constraint') !== false || 
        strpos($errorMsg, 'vinculado a un proceso') !== false) {
        json_response([
            "success" => false,
            "message" => "No se puede eliminar el ítem porque está vinculado a otro proceso (ej. producción o planificación)."
        ], 409);
    }

    json_response(["success" => false, "message" => $errorMsg], 500);
}