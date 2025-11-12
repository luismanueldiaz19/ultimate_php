<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php'; 
try {
    // 1️⃣ Obtener todos los pedidos con estado 'PENDIENTE'
    $query_pedidos = "SELECT inventario_pedidos_id, proveedor, fecha_creacion, estado, referencia, observaciones, creado_por, actualizado_en, fecha_waiting
                      FROM public.inventario_pedidos
                      WHERE estado = 'PENDIENTE'
                      ORDER BY fecha_creacion DESC";

    $result_pedidos = pg_query($conn, $query_pedidos);

    $pedidos = [];

    while ($pedido = pg_fetch_assoc($result_pedidos)) {
        $pedido_id = $pedido['inventario_pedidos_id'];

        // 2️⃣ Obtener detalles del pedido
        $query_detalles = "SELECT detalle_id, inventario_pedidos_id, id_producto, detalle_producto, cantidad, precio_unitario, subtotal
                           FROM public.inventario_pedido_detalles
                           WHERE inventario_pedidos_id = $1";

        $result_detalles = pg_query_params($conn, $query_detalles, [$pedido_id]);

        $detalles = [];
        while ($detalle = pg_fetch_assoc($result_detalles)) {
            $detalles[] = $detalle;
        }

        // 3️⃣ Agregar detalles al pedido
        $pedido['detalles'] = $detalles;
        $pedidos[] = $pedido;
    }

    echo json_encode([
        'success' => true,
        'data' => $pedidos
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener pedidos: ' . $e->getMessage()
    ]);
}

pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>