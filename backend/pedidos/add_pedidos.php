<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    $input = json_decode(file_get_contents('php://input'), true);

    $proveedor     = $input['proveedor'] ?? '';
    $referencia    = $input['referencia'] ?? '';
    $observaciones = $input['observaciones'] ?? '';
    $creado_por    = $input['creado_por'] ?? '';
    $fecha_waiting    = $input['fecha_waiting'] ?? '';
    $productos     = $input['productos'] ?? [];

    if (!$proveedor || !$creado_por || empty($productos)) {
        echo json_encode([
            'success' => false,
            'message' => 'Faltan datos obligatorios o lista de productos vacía.'
        ]);
        exit;
    }

    pg_query($conn, "BEGIN");

    try {
        // Insertar encabezado del pedido
        $insert_pedido = "INSERT INTO public.inventario_pedidos 
            (proveedor, fecha_creacion, estado, referencia, observaciones, creado_por, actualizado_en, fecha_waiting)
            VALUES ($1, now(), 'PENDIENTE', $2, $3, $4, now(), $5)
            RETURNING inventario_pedidos_id";

        $pedido_result = pg_query_params($conn, $insert_pedido, [
            $proveedor, $referencia, $observaciones, $creado_por,$fecha_waiting
        ]);
        $pedido_row = pg_fetch_assoc($pedido_result);
        $pedido_id = $pedido_row['inventario_pedidos_id'];

        // Insertar detalles
        foreach ($productos as $p) {
            $producto_id     = $p['producto_id'];
            $detalle         = $p['detalle_producto'] ?? '';
            $cantidad        = $p['cantidad'];
            $precio_unitario = $p['precio_unitario'];

            $insert_detalle = "INSERT INTO public.inventario_pedido_detalles 
                (inventario_pedidos_id, id_producto, detalle_producto, cantidad, precio_unitario)
                VALUES ($1, $2, $3, $4, $5)";
            pg_query_params($conn, $insert_detalle, [
                $pedido_id, $producto_id, $detalle, $cantidad, $precio_unitario
            ]);
        }

        pg_query($conn, "COMMIT");

        echo json_encode([
            'success' => true,
            'message' => 'Pedido registrado correctamente.',
            'pedido_id' => $pedido_id
        ]);

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => 'Error al registrar el pedido: ' . $e->getMessage()
        ]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>