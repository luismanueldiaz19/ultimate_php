<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    $input = json_decode(file_get_contents('php://input'), true);

    $fecha_inicio = $input['fecha_inicio'] ?? null;
    $fecha_fin    = $input['fecha_fin'] ?? null;
    $pagina       = max(1, intval($input['pagina'] ?? 1));
    $limite       = max(1, intval($input['limite'] ?? 10));
    $offset       = ($pagina - 1) * $limite;

    try {
        // 1️⃣ Construir query con filtros
        $query_pedidos = "SELECT inventario_pedidos_id, proveedor, fecha_creacion, estado, referencia, observaciones, creado_por, actualizado_en, fecha_waiting
                          FROM public.inventario_pedidos
                          WHERE estado = 'COMPLETADO'";

        $params = [];

        if ($fecha_inicio && $fecha_fin) {
            $query_pedidos .= " AND fecha_creacion BETWEEN $1 AND $2";
            $params = [$fecha_inicio, $fecha_fin];
        }

        $query_pedidos .= " ORDER BY fecha_creacion DESC LIMIT $limite OFFSET $offset";

        $result_pedidos = pg_query_params($conn, $query_pedidos, $params);

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

            $pedido['detalles'] = $detalles;
            $pedidos[] = $pedido;
        }

        echo json_encode([
            'success' => true,
            'pagina' => $pagina,
            'limite' => $limite,
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