<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    $input = json_decode(file_get_contents('php://input'), true);

    $pedido_id     = $input['inventario_pedidos_id'] ?? null;
    $proveedor     = $input['proveedor'] ?? '';
    $estado_nuevo  = $input['estado'] ?? '';
    $referencia    = $input['referencia'] ?? '';
    $observaciones = $input['observaciones'] ?? '';
    $almacen_id    = $input['almacen_id'] ?? null;
    $creado_por    = $input['creado_por'] ?? '';
    $fecha_waiting = $input['fecha_waiting'] ?? null;

    $estados_validos = ['PENDIENTE', 'PROCESADO', 'COMPLETADO', 'CANCELADO'];
    if (!in_array($estado_nuevo, $estados_validos)) {
        echo json_encode([
            'success' => false,
            'message' => 'Estado inválido. Debe ser uno de: ' . implode(', ', $estados_validos)
        ]);
        exit;
    }

    if (!$pedido_id || !$proveedor || !$estado_nuevo || !$referencia || !$almacen_id || !$creado_por) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
        exit;
    }

    pg_query($conn, "BEGIN");

    try {
        // 1️⃣ Verificar estado actual
        $check = pg_query_params($conn, "SELECT estado FROM public.inventario_pedidos WHERE inventario_pedidos_id = $1", [$pedido_id]);
        if (pg_num_rows($check) === 0) {
            throw new Exception("Pedido no encontrado.");
        }

        $estado_actual = pg_fetch_result($check, 0, 'estado');

        // 🚫 Si ya está COMPLETADO, no se puede modificar
        if ($estado_actual === 'COMPLETADO') {
            pg_query($conn, "ROLLBACK");
            echo json_encode([
                'success' => false,
                'message' => 'Este pedido ya fue COMPLETADO y no puede ser modificado.'
            ]);
            pg_close($conn);
            exit;
        }

        // 2️⃣ Actualizar pedido
        $update = "UPDATE public.inventario_pedidos
                   SET proveedor = $1, estado = $2, referencia = $3, observaciones = $4,
                       actualizado_en = now(), fecha_waiting = $5
                   WHERE inventario_pedidos_id = $6";
        pg_query_params($conn, $update, [$proveedor, $estado_nuevo, $referencia, $observaciones, $fecha_waiting, $pedido_id]);

        // 3️⃣ Si el nuevo estado es COMPLETADO → ingresar al inventario
        if ($estado_nuevo === 'COMPLETADO') {

            $detalles_result = pg_query_params($conn, "SELECT id_producto, cantidad, precio_unitario
                                                       FROM public.inventario_pedido_detalles
                                                       WHERE inventario_pedidos_id = $1", [$pedido_id]);

            while ($d = pg_fetch_assoc($detalles_result)) {
                $producto_id    = $d['id_producto'];
                $cantidad       = (float)$d['cantidad'];
                $costo_unitario = (float)$d['precio_unitario'];
                $motivo         = 'Ingreso por pedido completado';

                // Verificar si ya existe en inventario
                $inv_result = pg_query_params($conn, "SELECT stock_actual, costo_promedio
                                                      FROM public.inventario
                                                      WHERE producto_id = $1 AND almacen_id = $2", [$producto_id, $almacen_id]);

                if (pg_num_rows($inv_result) > 0) {
                    $inv = pg_fetch_assoc($inv_result);
                    $nuevo_stock = $inv['stock_actual'] + $cantidad;
                    $nuevo_costo = (($inv['stock_actual'] * $inv['costo_promedio']) + ($cantidad * $costo_unitario)) / $nuevo_stock;

                    pg_query_params($conn, "UPDATE public.inventario
                                            SET stock_actual = $1, costo_promedio = $2, actualizado_en = now()
                                            WHERE producto_id = $3 AND almacen_id = $4",
                                            [$nuevo_stock, $nuevo_costo, $producto_id, $almacen_id]);
                } else {
                    pg_query_params($conn, "INSERT INTO public.inventario
                                            (producto_id, almacen_id, stock_actual, stock_minimo, stock_maximo, costo_promedio, actualizado_en)
                                            VALUES ($1, $2, $3, 0, 0, $4, now())",
                                            [$producto_id, $almacen_id, $cantidad, $costo_unitario]);
                }

                // Insertar movimiento
                pg_query_params($conn, "INSERT INTO public.inventario_movimientos
                                        (producto_id, almacen_id, tipo_movimiento, cantidad, costo_unitario, referencia, motivo, creado_por, creado_en)
                                        VALUES ($1, $2, 'ENTRADA', $3, $4, $5, $6, $7, now())",
                                        [$producto_id, $almacen_id, $cantidad, $costo_unitario, $referencia, $motivo, $creado_por]);
            }
        }

        pg_query($conn, "COMMIT");

        echo json_encode([
            'success' => true,
            'message' => 'Pedido actualizado correctamente' . ($estado_nuevo === 'COMPLETADO' ? ' e inventario actualizado.' : '.')
        ]);

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>