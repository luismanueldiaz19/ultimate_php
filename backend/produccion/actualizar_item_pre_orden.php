<?php

include '../conexion.php';
include '../utils.php';

try {

    $data = json_decode(file_get_contents('php://input'), true);

  
$planificacion_work_id = isset($data['planificacion_work_id'])
    ? intval($data['planificacion_work_id'])
    : 0;

    $estado_item           = isset($data['estado_item'])
        ? strtoupper(trim($data['estado_item']))
        : null;

    $item_id = isset($data['item_pre_orden_id'])
        ? intval($data['item_pre_orden_id'])
        : 0;

    if (!$estado_item || $item_id <= 0 || !$planificacion_work_id) {
        throw new Exception('Datos invalidos: estado_item o item_pre_orden_id.');
    }

    pg_query($conn, 'BEGIN');

    // 🔹 1. Obtener pre_orden_id directamente del UPDATE
    $queryUpdateItem = '
        UPDATE public.item_pre_orden
        SET estado_item = $1
        WHERE item_pre_orden_id = $2
        RETURNING pre_orden_id
    ';

    $resUpdateItem = pg_query_params($conn, $queryUpdateItem, [
        $estado_item,
        $item_id
    ]);

    if (!$resUpdateItem || pg_num_rows($resUpdateItem) === 0) {
        pg_query($conn, 'ROLLBACK');
        throw new Exception('No se encontro el ítem o no se pudo actualizar.');
    }

    $row          = pg_fetch_assoc($resUpdateItem);
    $pre_orden_id = $row['pre_orden_id'];

    // 🔹 2. Verificar si quedan ítems pendientes (más eficiente)
    $queryPendientes = "
        SELECT COUNT(*) AS pendientes
        FROM public.item_pre_orden
        WHERE pre_orden_id = $1
        AND UPPER(TRIM(estado_item)) <> 'COMPLETADO'
    ";

    $resPendientes = pg_query_params($conn, $queryPendientes, [$pre_orden_id]);

    if (!$resPendientes) {
        pg_query($conn, 'ROLLBACK');
        throw new Exception('Error verificando items pendientes.');
    }

    $pendientes = (int) pg_fetch_result($resPendientes, 0, 'pendientes');

    $ordenActualizada = false;

    // select * from item_pre_orden where item_pre_orden_id = 120
    /// actualizar en la hoja de planificacion el estado sin reportar planilla de produccion de empleado

    if ($planificacion_work_id) {

        $queryUpdatePlanifi = '
        UPDATE public.planificacion_work
        SET estado_planificacion_work = $1
        WHERE planificacion_work_id = $2
    ';

        $resUpdatePlanifi = pg_query_params($conn, $queryUpdatePlanifi, [
            'COMPLETADO',
            $planificacion_work_id
        ]);

        if (!$resUpdatePlanifi) {
            throw new Exception('Error actualizando planificacion_work.');
        }
    }

    ///////////////// esta aqui

    // 🔹 3. Si no hay pendientes, actualizar la orden
    if ($pendientes === 0) {

        $queryUpdateOrden = "
            UPDATE public.pre_orden
            SET estado_general = 'POR ENTREGAR'
            WHERE pre_orden_id = $1
        ";

        $resUpdateOrden = pg_query_params($conn, $queryUpdateOrden, [$pre_orden_id]);

        if (!$resUpdateOrden) {
            pg_query($conn, 'ROLLBACK');
            throw new Exception('Error actualizando estado_general.');
        }

        $ordenActualizada = true;
    }

    pg_query($conn, 'COMMIT');

    json_response([
        'success' => true,
        'message' => $ordenActualizada
            ? 'Item actualizado y orden marcada como POR ENTREGAR.'
            : 'Item actualizado. Aun existen items pendientes.',
        'orden_actualizada' => $ordenActualizada,
        'pendientes'        => $pendientes
    ]);

} catch (Exception $e) {

    pg_query($conn, 'ROLLBACK');

    json_response([
        'success' => false,
        'message' => 'Error en la transaccion.',
        'error'   => $e->getMessage(),
    ], 500);
}
