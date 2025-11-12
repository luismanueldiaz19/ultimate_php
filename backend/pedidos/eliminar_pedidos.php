<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php'; 

    $input = json_decode(file_get_contents('php://input'), true);
    $pedido_id = $input['inventario_pedidos_id'] ?? null;

    if (!$pedido_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Falta el ID del pedido a eliminar.'
        ]);
        exit;
    }

    try {
        // 1️⃣ Verificar estado actual
        $check_query = "SELECT estado FROM public.inventario_pedidos WHERE inventario_pedidos_id = $1";
        $check_result = pg_query_params($conn, $check_query, [$pedido_id]);

        if (pg_num_rows($check_result) === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Pedido no encontrado.'
            ]);
            exit;
        }

        $row = pg_fetch_assoc($check_result);
        if ($row['estado'] === 'COMPLETADO') {
            echo json_encode([
                'success' => false,
                'message' => 'No se puede eliminar un pedido que ya está COMPLETADO.'
            ]);
            exit;
        }

        // 2️⃣ Eliminar pedido (detalles se eliminan por ON DELETE CASCADE)
        $delete_query = "DELETE FROM public.inventario_pedidos WHERE inventario_pedidos_id = $1";
        $delete_result = pg_query_params($conn, $delete_query, [$pedido_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Pedido eliminado correctamente.'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar el pedido: ' . $e->getMessage()
        ]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>