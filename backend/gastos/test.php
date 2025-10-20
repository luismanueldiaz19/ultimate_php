<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';


    $input = json_decode(file_get_contents('php://input'), true);

    // Campos obligatorios
    $fecha = $input['fecha'] ?? null;
    $id_proveedor = $input['id_proveedor'] ?? null;
    $tipo_gasto_id = $input['tipo_gasto_id'] ?? null;
    $descripcion = $input['descripcion'] ?? '';
    $monto_total = $input['monto_total'] ?? 0;
    $itbis = $input['itbis'] ?? 0;
    $forma_pago = $input['forma_pago'] ?? '';
    $centro_costo = $input['centro_costo'] ?? '';
    $comprobante_pago = $input['comprobante_pago'] ?? '';
    $factura_adjunta = $input['factura_adjunta'] ?? '';
    $usuario_registro_id = $input['usuario_registro_id'] ?? null;

    if (!$fecha || !$id_proveedor || !$tipo_gasto_id || !$monto_total || !$forma_pago) {
        echo json_encode(['success'=>false, 'message'=>'Faltan campos obligatorios']);
        exit;
    }

    try {
        // Iniciar transacción
        pg_query($conn, "BEGIN");

        // 1️⃣ Insertar gasto
        $insert_gasto = "INSERT INTO public.gasto_empresa
            (fecha, id_proveedor, tipo_gasto_id, descripcion, monto_total, itbis, forma_pago, centro_costo, comprobante_pago, factura_adjunta, usuario_registro_id)
            VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11) RETURNING gasto_empresa_id";
        $res_gasto = pg_query_params($conn, $insert_gasto, [
            $fecha, $id_proveedor, $tipo_gasto_id, $descripcion,
            $monto_total, $itbis, $forma_pago, $centro_costo,
            $comprobante_pago, $factura_adjunta, $usuario_registro_id
        ]);

        if (!$res_gasto) throw new Exception(pg_last_error($conn));
        $gasto_id = pg_fetch_result($res_gasto, 0, 'gasto_empresa_id');

        // 2️⃣ Crear asiento contable
        $desc_asiento = $descripcion . " - Gasto ID $gasto_id";
        $insert_asiento = "INSERT INTO public.asientos_contables (fecha, descripcion, estado)
                           VALUES ($1,$2,'registrado') RETURNING id_asiento";
        $res_asiento = pg_query_params($conn, $insert_asiento, [$fecha, $desc_asiento]);
        if (!$res_asiento) throw new Exception(pg_last_error($conn));
        $asiento_id = pg_fetch_result($res_asiento, 0, 'id_asiento');

        // 3️⃣ Actualizar gasto con el asiento
        $update_gasto = "UPDATE public.gasto_empresa SET asiento_contable_id=$1 WHERE gasto_empresa_id=$2";
        $res_update = pg_query_params($conn, $update_gasto, [$asiento_id, $gasto_id]);
        if (!$res_update) throw new Exception(pg_last_error($conn));

        // 4️⃣ Obtener plantilla asociada al tipo de gasto
        $query_plantilla = "
            SELECT pc.codigo_cuenta, pc.tipo_movimiento
            FROM tipo_gasto tg
            JOIN plantilla_contable p ON tg.plantilla_id = p.plantilla_id
            JOIN plantilla_cuentas pc ON p.plantilla_id = pc.plantilla_id
            WHERE tg.tipo_gasto_id = $1
            ORDER BY pc.orden
        ";
        $res_plantilla = pg_query_params($conn, $query_plantilla, [$tipo_gasto_id]);
        if (!$res_plantilla) throw new Exception(pg_last_error($conn));

        // 5️⃣ Insertar detalles de asiento según plantilla
        $total_asiento = 0;
        while ($row = pg_fetch_assoc($res_plantilla)) {
            $codigo = $row['codigo_cuenta'];
            $mov = $row['tipo_movimiento']; // DEBE o HABER
            $debe = 0;
            $haber = 0;

            // Asignar montos según lógica: ITBIS y monto sin ITBIS
            if ($codigo === '2.0.6') { // ITBIS por pagar
                $debe = $itbis;
                $total_asiento += $debe;
            } elseif ($mov === 'DEBE') { // Gasto neto
                $debe = $monto_total - $itbis;
                $total_asiento += $debe;
            } elseif ($mov === 'HABER') { // Pago
                $haber = $monto_total;
                $total_asiento += $haber; // opcional, para total general
            }

            $insert_detalle = "INSERT INTO public.detalle_asiento (id_asiento, codigo_cuenta, debe, haber)
                               VALUES ($1,$2,$3,$4)";
            $res_detalle = pg_query_params($conn, $insert_detalle, [$asiento_id, $codigo, $debe, $haber]);
            if (!$res_detalle) throw new Exception(pg_last_error($conn));
        }

        // 6️⃣ Actualizar total del asiento
        $update_total = "UPDATE public.asientos_contables SET total=$1 WHERE id_asiento=$2";
        $res_total = pg_query_params($conn, $update_total, [$monto_total, $asiento_id]);
        if (!$res_total) throw new Exception(pg_last_error($conn));

        // 7️⃣ Confirmar transacción
        pg_query($conn, "COMMIT");

        echo json_encode([
            'success' => true,
            'gasto_id' => $gasto_id,
            'asiento_id' => $asiento_id,
            'total_asiento' => $monto_total
        ]);

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, "message" => "Método no permitido"]);
}
?>
