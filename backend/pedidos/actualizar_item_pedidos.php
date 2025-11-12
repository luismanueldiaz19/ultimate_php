<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    $input = json_decode(file_get_contents('php://input'), true);

    $detalle_id             = $input['detalle_id'] ?? null;
    $inventario_pedidos_id  = $input['inventario_pedidos_id'] ?? null;
    $cantidad               = $input['cantidad'] ?? null;
    $precio_unitario        = $input['precio_unitario'] ?? null;

    // Validar campos obligatorios
    if (!$detalle_id || !$inventario_pedidos_id || $cantidad === null || $precio_unitario === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Faltan datos obligatorios para actualizar el detalle.'
        ]);
        exit;
    }

    try {
        // 1️⃣ Verificar estado del pedido principal
        $check_estado = pg_query_params($conn,
            "SELECT estado FROM public.inventario_pedidos WHERE inventario_pedidos_id = $1",
            [$inventario_pedidos_id]
        );

        if (pg_num_rows($check_estado) === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Pedido principal no encontrado.'
            ]);
            exit;
        }

        $estado = pg_fetch_result($check_estado, 0, 'estado');

        // 🚫 Si el pedido está COMPLETADO, no se puede modificar el detalle
        if ($estado === 'COMPLETADO') {
            echo json_encode([
                'success' => false,
                'message' => 'Este pedido ya fue COMPLETADO y sus detalles no pueden ser modificados.'
            ]);
            exit;
        }

        // 2️⃣ Actualizar detalle
        $update_query = "UPDATE public.inventario_pedido_detalles
                         SET cantidad = $1, precio_unitario = $2
                         WHERE detalle_id = $3";

        $result = pg_query_params($conn, $update_query, [$cantidad, $precio_unitario, $detalle_id]);

        if (pg_affected_rows($result) > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Detalle actualizado correctamente.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se encontró el detalle con ese ID.'
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar: ' . $e->getMessage()
        ]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>