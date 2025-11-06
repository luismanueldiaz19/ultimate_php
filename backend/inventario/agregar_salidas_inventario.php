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

    try {
        foreach ($productos as $p) {
            $producto_id   = $p['producto_id'];
            $cantidad      = $p['cantidad'];
            $costo_unitario = $p['costo_unitario'];

            // 1️⃣ Verificar si el producto ya existe en inventario
            $check_query = "SELECT stock_actual, costo_promedio FROM public.inventario 
                            WHERE producto_id = $1 AND almacen_id = $2";
            $check_result = pg_query_params($conn, $check_query, [$producto_id, $almacen_id]);

            if (pg_num_rows($check_result) > 0) {
                // Ya existe → actualizar stock y costo promedio
                $row = pg_fetch_assoc($check_result);
                $nuevo_stock = $row['stock_actual'] + $cantidad;

                // Recalcular costo promedio simple
                $nuevo_costo_prom = (($row['stock_actual'] * $row['costo_promedio']) + ($cantidad * $costo_unitario)) / $nuevo_stock;

                $update_query = "UPDATE public.inventario 
                                 SET stock_actual = $1, costo_promedio = $2, actualizado_en = now() 
                                 WHERE producto_id = $3 AND almacen_id = $4";
                pg_query_params($conn, $update_query, [$nuevo_stock, $nuevo_costo_prom, $producto_id, $almacen_id]);

            } else {
                // No existe → crear nuevo registro en inventario
                $insert_inventario = "INSERT INTO public.inventario 
                    (producto_id, almacen_id, stock_actual, stock_minimo, stock_maximo, costo_promedio, actualizado_en)
                    VALUES ($1, $2, $3, 0, 0, $4, now())";
                pg_query_params($conn, $insert_inventario, [$producto_id, $almacen_id, $cantidad, $costo_unitario]);
            }

            // 2️⃣ Insertar movimiento de entrada
            $insert_mov = "INSERT INTO public.inventario_movimientos 
                (producto_id, almacen_id, tipo_movimiento, cantidad, costo_unitario, referencia, motivo, creado_por, creado_en)
                VALUES ($1, $2, 'ENTRADA', $3, $4, $5, $6, $7, now())";
            pg_query_params($conn, $insert_mov, [
                $producto_id, $almacen_id, $cantidad, $costo_unitario, $referencia, $motivo, $creado_por
            ]);
        }

        // Confirmar transacción
        pg_query($conn, "COMMIT");

        echo json_encode([
            'success' => true,
            'message' => 'Entrada al inventario registrada correctamente.'
        ]);

    } catch (Exception $e) {
        // Revertir si hay error
        pg_query($conn, "ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => 'Error en transacción: ' . $e->getMessage()
        ]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
