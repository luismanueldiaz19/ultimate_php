<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    $input = json_decode(file_get_contents('php://input'), true);

    $orden_id = $input['orden_compra_id'];
    $num_factura = $input['num_factura'];
    $fecha_aprobacion = date('Y-m-d H:i:s'); // ahora
    $detalles = $input['detalles_compras'];

    pg_query($conn, "BEGIN");


   // Validar estado actual de la orden
$estado_query = "SELECT estado FROM orden_compra WHERE orden_compra_id = $1";
$res_estado = pg_query_params($conn, $estado_query, [$orden_id]);

if (!$res_estado || pg_num_rows($res_estado) === 0) {
    echo json_encode(['success' => false, 'message' => 'Orden no encontrada']);
    exit;
}

$estado_actual = pg_fetch_result($res_estado, 0, 'estado');

if ($estado_actual === 'aprobada' || $estado_actual === 'cerrada') {
    echo json_encode([
        'success' => false,
        'message' => 'La orden ya fue aprobada. No se puede volver a procesar.'
    ]);
    exit;
}
 


    // 1. Actualizar estado y fecha
    $update_estado = "UPDATE orden_compra SET estado = 'aprobada', fecha_aprobacion = $1 WHERE orden_compra_id = $2";

    $res_estado = pg_query_params($conn, $update_estado, [$fecha_aprobacion, $orden_id]);

    if (!$res_estado) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(['success' => false, 'message' => 'Error al actualizar estado']);
        exit;
    }
    // SELECT id_asiento, fecha, descripcion, total, estado, id_cliente FROM public.asientos_contables;
    // 2. Insertar asiento contable
    $insert_asiento = "
    INSERT INTO asientos_contables (fecha, descripcion, estado)
    VALUES ($1, $2, $3)
    RETURNING id_asiento
";

$nota = $num_factura . ' - Orden de compra aprobada';

$res_asiento = pg_query_params($conn, $insert_asiento, [$fecha_aprobacion, $nota, 'confirmado']);

if (!$res_asiento) {
    pg_query($conn, "ROLLBACK");
    echo json_encode(['success' => false, 'message' => 'Error al crear asiento contable']);
    exit;
}

$id_asiento = pg_fetch_result($res_asiento, 0, 'id_asiento');

    $errores = [];

    $total_asiento = 0;

    foreach ($detalles as $detalle) {
        $p = $detalle['producto'];
        $id_producto = $p['id_producto'];
        $cantidad = $p['cantidad'];
        $costo = $p['costo'];
        $total = $p['total'];
        $cuenta_inventario = $p['cuenta_costo'];
        $cuenta_cxp = '2.0.6'; 

        // ITBIS por pagar u otra cuenta de pasivo
        // 3. Insertar movimiento de inventario

       //SELECT movimiento_inventario_id, id_producto, tipo, cantidad, costo_unitario, referencia, fecha  FROM public.movimiento_inventario;
        $mov_inv = "  INSERT INTO movimiento_inventario (id_producto, tipo, cantidad, costo_unitario, referencia, fecha) VALUES ($1, 'entrada', $2, $3, $4, $5)";
        $res_mov = pg_query_params($conn, $mov_inv, [$id_producto, $cantidad, $costo, $num_factura, $fecha_aprobacion]);
        if (!$res_mov) {
            $errores[] = "Inventario: " . pg_last_error($conn);
            continue;
        }

        // 4. Actualizar inventario
        // SELECT id, id_producto, cantidad, costo_unitario, fecha FROM public.inventario;
        $update_inv = "
            INSERT INTO inventario (id_producto, cantidad, costo_unitario)
            VALUES ($1, $2, $3)
            ON CONFLICT (id_producto)
            DO UPDATE SET cantidad = inventario.cantidad + $2, costo_unitario = $3
        ";
        $res_inv = pg_query_params($conn, $update_inv, [$id_producto, $cantidad, $costo]);
        if (!$res_inv) {
            $errores[] = "Actualización inventario: " . pg_last_error($conn);
            continue;
        }
        // SELECT id_detalle, id_asiento, codigo_cuenta, debe, haber FROM public.detalle_asiento;
        // 5. Insertar asiento detalle (Debe: Inventario)
        $res_debe = pg_query_params($conn, "
            INSERT INTO detalle_asiento (id_asiento, codigo_cuenta, debe, haber)
            VALUES ($1, $2, $3, 0)
        ", [$id_asiento, $cuenta_inventario, $total]);

        // 6. Insertar asiento detalle (Haber: CxP)
        $res_haber = pg_query_params($conn, "
            INSERT INTO detalle_asiento (id_asiento, codigo_cuenta, debe, haber)
            VALUES ($1, $2, 0, $3)
        ", [$id_asiento, $cuenta_cxp, $total]);

        if (!$res_debe || !$res_haber) {
            $errores[] = "Asiento contable: " . pg_last_error($conn);
        }

        $total_asiento += $total;
    }

    if (!empty($errores)) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(['success' => false, 'message' => 'Errores en el proceso', 'errors' => $errores]);
    } else {
        pg_query($conn, "COMMIT");
        echo json_encode([
            'success' => true,
            'message' => 'Orden aprobada, inventario actualizado y asiento contable generado',
            'asiento_id' => $id_asiento,
            'total_asiento' => $total_asiento
        ]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, "message" => "Método no permitido"]);
}
?>