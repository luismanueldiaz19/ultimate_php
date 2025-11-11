<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    // Leer el JSON recibido
    $input = json_decode(file_get_contents('php://input'), true);

    $almacen_id = $input['almacen_id'] ?? null;
    $realizado_por = $input['realizado_por'] ?? null;
    $motivo = $input['motivo'] ?? 'Ajuste de inventario';
    $productos = $input['productos'] ?? [];

    // Validar campos obligatorios
    if (!$almacen_id || !$realizado_por || empty($productos)) {
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
            $producto_id = $p['producto_id'];
            $cantidad_esperada = floatval($p['cantidad_esperada']);
            $cantidad_real = floatval($p['cantidad_real']);
            $costo_unitario = floatval($p['costo_unitario']);

            // Calcular diferencia
            $diferencia = $cantidad_real - $cantidad_esperada;

            $tipo_movimiento = $diferencia >= 0 ? 'AJUSTE POSITIVO' : 'AJUSTE NEGATIVO';

         

            // 🔹 Insertar en inventario_ajustes
            $insert_ajuste = "INSERT INTO public.inventario_ajustes 
                (producto_id, almacen_id, cantidad_esperada, cantidad_real, diferencia, motivo, realizado_por, costo_unitario)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8)";
            pg_query_params($conn, $insert_ajuste, [
                $producto_id, $almacen_id, $cantidad_esperada, $cantidad_real, $diferencia,
                $motivo, $realizado_por, $costo_unitario
            ]);

            // 🔹 Verificar si existe en inventario
            $check_query = "SELECT stock_actual, costo_promedio FROM public.inventario 
                            WHERE producto_id = $1 AND almacen_id = $2";
            $check_result = pg_query_params($conn, $check_query, [$producto_id, $almacen_id]);

            if (pg_num_rows($check_result) > 0) {
                $row = pg_fetch_assoc($check_result);
                $nuevo_stock = $row['stock_actual'] + $diferencia;
                if ($nuevo_stock < 0) $nuevo_stock = 0; // Evitar stock negativo

                $update_query = "UPDATE public.inventario 
                                 SET stock_actual = $1, actualizado_en = now() 
                                 WHERE producto_id = $2 AND almacen_id = $3";
                pg_query_params($conn, $update_query, [$nuevo_stock, $producto_id, $almacen_id]);
            } else {
                // Si no existe, lo creamos con la cantidad real
                $insert_inventario = "INSERT INTO public.inventario 
                    (producto_id, almacen_id, stock_actual, stock_minimo, stock_maximo, costo_promedio, actualizado_en)
                    VALUES ($1, $2, $3, 0, 0, $4, now())";
                pg_query_params($conn, $insert_inventario, [
                    $producto_id, $almacen_id, $cantidad_real, $costo_unitario
                ]);
            }

            // $cantidad_negativa =    $tipo_movimiento == 'AJUSTE NEGATIVO' ? -1 * abs($diferencia): 

            // 🔹 Registrar movimiento general
            $insert_mov = "INSERT INTO public.inventario_movimientos 
                (producto_id, almacen_id, tipo_movimiento, cantidad, costo_unitario, referencia, motivo, creado_por, creado_en)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, now())";
            pg_query_params($conn, $insert_mov, [
                $producto_id, $almacen_id, $tipo_movimiento, $diferencia, $costo_unitario,
                'AJUSTE-' . date('YmdHis'), $motivo, $realizado_por
            ]);

            // 🔹 Registrar pérdida solo si el ajuste es negativo
            if ($diferencia < 0) {
                $cantidad_perdida = abs($diferencia);

                $total_perdida = $cantidad_perdida * $costo_unitario;

                $insert_perdida = "INSERT INTO public.inventario_perdidas (producto_id, almacen_id, cantidad, costo_unitario, total_perdida, referencia, motivo, creado_por)
                    VALUES ($1, $2, $3, $4, $5, $6, $7, $8)";
                pg_query_params($conn, $insert_perdida, [
                    $producto_id, $almacen_id, $cantidad_perdida, $costo_unitario,
                    $total_perdida, 'PERDIDA-' . date('YmdHis'), $motivo, $realizado_por
                ]);
            }
        }

        pg_query($conn, "COMMIT");
        echo json_encode([
            'success' => true,
            'message' => 'Ajuste de inventario registrado correctamente.'
        ]);

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => 'Error en transacción: ' . $e->getMessage()
        ]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}
?>
