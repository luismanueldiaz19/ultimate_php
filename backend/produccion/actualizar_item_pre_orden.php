<?php
include '../conexion.php';
include '../utils.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    $estado_item = $data['estado_item'] ?? null;
    $item_id = $data['item_pre_orden_id'] ?? null;
    $pre_orden_id = $data['pre_orden_id'] ?? null;

    if (!$estado_item || !$item_id || !$pre_orden_id) {
        throw new Exception("Faltan campos requeridos: estado_item, item_pre_orden_id o pre_orden_id.");
    }

    pg_query($conn, "BEGIN");

    // 1. Actualizar el estado del ítem
    $queryUpdateItem = "UPDATE public.item_pre_orden SET estado_item = $1 WHERE item_pre_orden_id = $2";

    $resUpdateItem = pg_query_params($conn, $queryUpdateItem, [$estado_item, $item_id]);

    if (!$resUpdateItem) {
        pg_query($conn, "ROLLBACK");
        throw new Exception("Error al actualizar el estado del ítem.");
    }

    // 2. Verificar si todos los ítems están completados
    $queryCheckItems = "SELECT estado_item FROM public.item_pre_orden WHERE pre_orden_id = $1";
    
    $resCheckItems = pg_query_params($conn, $queryCheckItems, [$pre_orden_id]);

    if (!$resCheckItems) {
        pg_query($conn, "ROLLBACK");
        throw new Exception("Error al consultar los ítems.");
    }

    $allCompletados = true;
    while ($row = pg_fetch_assoc($resCheckItems)) {
        if (strtoupper(trim($row['estado_item'])) !== 'COMPLETADO') {
            $allCompletados = false;
            break;
        }
    }

    // 3. Si todos están completados, actualizar la orden
    if ($allCompletados) {
        $queryUpdateOrden = "UPDATE public.pre_orden SET estado_general = 'POR ENTREGAR' WHERE pre_orden_id = $1";
        $resUpdateOrden = pg_query_params($conn, $queryUpdateOrden, [$pre_orden_id]);

        if (!$resUpdateOrden) {
            pg_query($conn, "ROLLBACK");
            throw new Exception("Error al actualizar el estado de la orden.");
        }
    }

    pg_query($conn, "COMMIT");

    json_response([
        'success' => true,
        'message' => $allCompletados
            ? 'Ítem actualizado y orden marcada como POR ENTREGAR.'
            : 'Ítem actualizado. La orden aún tiene ítems pendientes.',
    ]);
} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    json_response([
        'success' => false,
        'message' => 'Error en el proceso.',
        'error' => $e->getMessage(),
    ], 500);
}