<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    // Leer el JSON recibido
    $input = json_decode(file_get_contents('php://input'), true);

    $almacen_id = $input['almacen_id'] ?? null;
    $creado_por = $input['creado_por'] ?? null;
    $referencia = $input['referencia'] ?? '';
    $motivo     = $input['motivo'] ?? '';
    $productos  = $input['productos'] ?? [];

    // Validar campos obligatorios
    if (!$almacen_id || !$creado_por || empty($productos)) {
        echo json_encode([
            'success' => false,
            'message' => 'Faltan datos obligatorios o lista de productos vacía.'
        ]);
        exit;
    }

    // Iniciar transacción
    pg_query($conn, "BEGIN");

    $response = [
        'success' => true,
        'message' => 'Proceso completado con observaciones.',
        'procesados' => [],
        'errores' => []
    ];

    foreach ($productos as $p) {
        $producto_id    = $p['producto_id'];
        $cantidad       = $p['cantidad'];
        $costo_unitario = $p['costo_unitario'];

        try {
            // Verificar existencia
            $check_query = "SELECT stock_actual FROM public.inventario WHERE producto_id = $1 AND almacen_id = $2";
            $check_result = pg_query_params($conn, $check_query, [$producto_id, $almacen_id]);

            if (pg_num_rows($check_result) === 0) {
                throw new Exception("Producto no existe en inventario.");
            }

            $row = pg_fetch_assoc($check_result);
            $stock_actual = $row['stock_actual'];

            if ($stock_actual < $cantidad) {
                throw new Exception("Stock insuficiente.");
            }

            $nuevo_stock = $stock_actual - $cantidad;

            // Actualizar inventario
            $update_query = "UPDATE public.inventario 
                             SET stock_actual = $1, actualizado_en = now() 
                             WHERE producto_id = $2 AND almacen_id = $3";
            pg_query_params($conn, $update_query, [$nuevo_stock, $producto_id, $almacen_id]);

            // Registrar movimiento
            $tipo_movimiento = strtoupper(trim($motivo));
            $cantidad_negativa = -1 * abs($cantidad); 

            $insert_mov = "INSERT INTO public.inventario_movimientos 
                (producto_id, almacen_id, tipo_movimiento, cantidad, costo_unitario, referencia, motivo, creado_por, creado_en)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, now())";
            pg_query_params($conn, $insert_mov, [
                $producto_id, $almacen_id, $tipo_movimiento, $cantidad_negativa, $costo_unitario, $referencia, $motivo, $creado_por
            ]);

            $response['procesados'][] = [
                'producto_id' => $producto_id,
                'cantidad' => $cantidad_negativa,
                'nuevo_stock' => $nuevo_stock,
                'success' => true
            ];

        } catch (Exception $e) {
            $response['success'] = false;
            $response['errores'][] = [
                'producto_id' => $producto_id,
                'cantidad' => $cantidad,
                'error' => $e->getMessage()
            ];
        }
    }

    // Confirmar o revertir según errores
    if ($response['success']) {
        pg_query($conn, "COMMIT");
    } else {
        pg_query($conn, "ROLLBACK");
        $response['message'] = 'Se encontraron errores en algunos productos. Transacción revertida.';
    }

    pg_close($conn);
    echo json_encode($response);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>