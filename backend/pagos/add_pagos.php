<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Validar campos obligatorios
    $requiredFields = ['pre_orden_id', 'monto_pago', 'id_usuario'];
    $missingFields = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        json_response([
            "success" => false,
            "message" => "Faltan campos obligatorios",
            "faltantes" => $missingFields
        ], 400);
    }

    // Sanitizar y preparar datos
    $preOrdenId = intval($data['pre_orden_id']);
    $monto = number_format(floatval($data['monto_pago']), 2, '.', '');
    $metodo = isset($data['metodo_pago']) ? trim($data['metodo_pago']) : 'EFECTIVO';
    $referencia = isset($data['referencia_pago']) ? trim($data['referencia_pago']) : 'N/A';
    $id_usuario = trim($data['id_usuario']);
    $observacion = isset($data['observacion']) ? trim($data['observacion']) : 'N/A';

    // Iniciar transacción
    pg_query($conn, "BEGIN");

    // Validar total pagado
    $pagadoRes = pg_query_params($conn,
        "SELECT COALESCE(SUM(monto_pago), 0) AS total_pagado FROM pagos_pre_orden WHERE pre_orden_id = $1",
        [$preOrdenId]
    );
    if (!$pagadoRes) {
        pg_query($conn, "ROLLBACK");
        json_response(["success" => false, "message" => "Error al consultar pagos anteriores"], 500);
    }
    $pagado = floatval(pg_fetch_result($pagadoRes, 0, 'total_pagado'));

    // Validar total de la orden
    $ordenRes = pg_query_params($conn,
        "SELECT total_final FROM pre_orden WHERE pre_orden_id = $1",
        [$preOrdenId]
    );
    if (!$ordenRes || pg_num_rows($ordenRes) === 0) {
        pg_query($conn, "ROLLBACK");
        json_response(["success" => false, "message" => "La orden no existe"], 404);
    }
    $totalOrden = floatval(pg_fetch_result($ordenRes, 0, 'total_final'));

    // Validar exceso
    if (($pagado + $monto) > $totalOrden) {
        pg_query($conn, "ROLLBACK");
        json_response([
            "success" => false,
            "message" => "El pago excede el total de la orden. Ya se han pagado $pagado de $totalOrden."
        ], 400);
    }

    // Insertar pago
    $sql = "INSERT INTO public.pagos_pre_orden (
                pre_orden_id, monto_pago, metodo_pago, referencia_pago, id_usuario, observacion
            ) VALUES ($1, $2, $3, $4, $5, $6)
            RETURNING pago_id, pre_orden_id, monto_pago, metodo_pago, referencia_pago, id_usuario, fecha_pago, observacion";

    $params = [$preOrdenId, $monto, $metodo, $referencia, $id_usuario, $observacion];
    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        pg_query($conn, "ROLLBACK");
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al registrar el pago: " . $error], 500);
    }
     

    $totalPagadoActual = $pagado + $monto;
    $pendiente = $totalOrden - $totalPagadoActual;
    $estadoPago = ($pendiente <= 0) ? 'COMPLETO' : 'PENDIENTE';


    // Confirmar transacción
    pg_query($conn, "COMMIT");

    $newPago = pg_fetch_assoc($result);

    // Marcar la orden como facturada si aún no lo está
    $facturar = pg_query_params($conn,"UPDATE public.pre_orden SET is_facturado = true WHERE pre_orden_id = $1 AND is_facturado = false",[$preOrdenId]);
       if (!$facturar) {
              pg_query($conn, "ROLLBACK");
              json_response(["success" => false, "message" => "Error al marcar la orden como facturada"], 500);}









    json_response([
        "success" => true,
        "message" => "Pago registrado exitosamente",
        "pago" => array_merge($newPago, [
        "total_factura" => number_format($totalOrden, 2, '.', ''),
        "total_pagado" => number_format($totalPagadoActual, 2, '.', ''),
        "pendiente" => number_format($pendiente, 2, '.', ''),
        "estado_pago" => $estadoPago
    ])

    ]);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>