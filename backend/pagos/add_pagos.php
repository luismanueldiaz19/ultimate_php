<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Validar campos obligatorios
    $requiredFields = ['pre_orden_id', 'monto_pagado', 'id_usuario', 'id_cliente', 'metodo_pago', 'monto_factura', 'itbis','codigo_cuenta_cxc', 'num_orden'];
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


    // Sanitizar datos
    $preOrdenId = intval($data['pre_orden_id']);
    $idCliente = $data['id_cliente'];
    $idUsuario = trim($data['id_usuario']);
    $numOrden = trim($data['num_orden']);
    $codigo_cuenta_cxc = trim($data['codigo_cuenta_cxc']);
    $montoPagado = number_format(floatval($data['monto_pago']), 2, '.', '');
    $montoFactura = number_format(floatval($data['monto_factura']), 2, '.', '');
    $itbis = number_format(floatval($data['itbis']), 2, '.', '');
    $metodo = strtolower(trim($data['metodo_pago']));
    $referencia = isset($data['referencia_pago']) ? trim($data['referencia_pago']) : 'N/A';
    $observacion = isset($data['observacion']) ? trim($data['observacion']) : 'N/A';

    if ($montoPagado <= 0  ) {
       throw new Exception("No se puede registrar un pago de monto cero.");
    }
     if ($montoPagado > $montoFactura) {
       throw new Exception("El monto aplicado excede el total de la factura.");
     }


    // Iniciar transacción
    pg_query($conn, "BEGIN");



    

    // Validar pagos anteriores
    $pagadoRes = pg_query_params($conn,
        "SELECT COALESCE(SUM(monto_pago), 0) AS total_pagado FROM pagos_pre_orden WHERE pre_orden_id = $1",
        [$preOrdenId]
    );
    if (!$pagadoRes) {
        pg_query($conn, "ROLLBACK");
        json_response(["success" => false, "message" => "Error al consultar pagos anteriores"], 500);
    }
    $pagadoAnterior = floatval(pg_fetch_result($pagadoRes, 0, 'total_pagado'));

    // Validar exceso
    if (($pagadoAnterior + $montoPagado) > $montoFactura) {
        pg_query($conn, "ROLLBACK");
        json_response([
            "success" => false,
            "message" => "El pago excede el total de la factura. Ya se han pagado $pagadoAnterior de $montoFactura."
        ], 400);
    }

    // Insertar pago
    $sqlPago = "INSERT INTO pagos_pre_orden (
        pre_orden_id, monto_pago, metodo_pago, referencia_pago, id_usuario, observacion
    ) VALUES ($1, $2, $3, $4, $5, $6)
    RETURNING pago_id, fecha_pago";
    
    $paramsPago = [$preOrdenId, $montoPagado, $metodo, $referencia, $idUsuario, $observacion];

    $resPago = pg_query_params($conn, $sqlPago, $paramsPago);
    if (!$resPago) {
        pg_query($conn, "ROLLBACK");
        json_response(["success" => false, "message" => "Error al registrar el pago"], 500);
    }
 

     $cuentaItbisRes = pg_query_params($conn,"SELECT codigo_cuenta FROM parametros_contables WHERE nombre_operacion = 'itbis_por_pagar'",[]);
      if (!$cuentaItbisRes) {
              pg_query($conn, "ROLLBACK");
              json_response(["success" => false, "message" => "Error al obtener cuenta de ITBIS"], 500);
        }
       $codigoCuentaItbis = pg_fetch_result($cuentaItbisRes, 0, 'codigo_cuenta'); 


       $cuentaIngresoRes = pg_query_params($conn,"SELECT codigo_cuenta FROM parametros_contables WHERE nombre_operacion = 'ingreso_ventas'",[]);
                           if (!$cuentaIngresoRes) {
                              pg_query($conn, "ROLLBACK");
                              json_response(["success" => false, "message" => "Error al obtener cuenta de ingreso"], 500);
                     }
       $codigoCuentaIngreso = pg_fetch_result($cuentaIngresoRes, 0, 'codigo_cuenta');
     


    // Obtener cuentas contables
    $cuentaPagoRes = pg_query_params($conn,
        "SELECT codigo_cuenta FROM parametros_contables WHERE nombre_operacion = $1",
        [$metodo]
    );
    // $cuentaCxcRes = pg_query_params($conn,
    //     "SELECT codigo_cuenta FROM parametros_contables WHERE nombre_operacion = 'cxc_clientes'"
    // );
    if (!$cuentaPagoRes) {
        pg_query($conn, "ROLLBACK");
        json_response(["success" => false, "message" => "Error al obtener cuentas contables"], 500);
    }
    $codigoCuentaPago = pg_fetch_result($cuentaPagoRes, 0, 'codigo_cuenta');
    
    // $codigoCuentaCxc = pg_fetch_result($cuentaCxcRes, 0, 'codigo_cuenta');

    // Insertar asiento contable
       $descripcionAsiento = ($montoPagado < $montoFactura) ? "Pago parcial de Número Orden #$numOrden" : "Pago total de Número Orden #$numOrden";
     

    $itbisProporcional = number_format(($montoPagado / $montoFactura) * $itbis, 2, '.', '');
    $baseProporcional = number_format($montoPagado - $itbisProporcional, 2, '.', '');




    // SELECT id_asiento, fecha, descripcion, id_cliente, total
	// FROM public.asientos_contables;
    $sqlAsiento = "INSERT INTO asientos_contables (descripcion, id_cliente, total, estado)
                   VALUES ($1, $2, $3, 'confirmado') RETURNING id_asiento";
    $resAsiento = pg_query_params($conn, $sqlAsiento, [$descripcionAsiento, $idCliente, $montoPagado]);
    if (!$resAsiento) {
        pg_query($conn, "ROLLBACK");
        json_response(["success" => false, "message" => "Error al crear asiento contable"], 500);
    }
    $idAsiento = pg_fetch_result($resAsiento, 0, 'id_asiento');

    // Insertar detalle del asiento
    $sqlDetalle = "INSERT INTO detalle_asiento (id_asiento, codigo_cuenta, debe, haber) VALUES ($1, $2, $3, $4)";
   
    // Debe: método de pago recibido
     pg_query_params($conn, $sqlDetalle, [$idAsiento, $codigoCuentaPago, $montoPagado, 0.00]);

    // Haber: ingreso neto
    pg_query_params($conn, $sqlDetalle, [$idAsiento, $codigoCuentaIngreso, 0.00, $baseProporcional]);

    // Haber: ITBIS generado
    pg_query_params($conn, $sqlDetalle, [$idAsiento, $codigoCuentaItbis, 0.00, $itbisProporcional]);

 
    // Marcar orden como facturada si aplica
    pg_query_params($conn,
        "UPDATE pre_orden SET is_facturado = true WHERE pre_orden_id = $1 AND is_facturado = false",
        [$preOrdenId]
    );

    // Confirmar transacción
    pg_query($conn, "COMMIT");

    $pendiente = $montoFactura - ($pagadoAnterior + $montoPagado);
    $estadoPago = ($pendiente <= 0) ? 'COMPLETO' : 'PENDIENTE';

    json_response([
        "success" => true,
        "message" => "Pago y asiento contable registrados correctamente",
        "pago" => [
            "pre_orden_id" => $preOrdenId,
            "monto_pagado" => $montoPagado,
            "total_factura" => $montoFactura,
            "itbis" => $itbis,
            "pendiente" => $pendiente,
            "estado_pago" => $estadoPago,
            "metodo_pago" => strtoupper($metodo),
            "codigo_cuenta_pago" => $codigoCuentaPago,
            "codigo_cuenta_cxc" => $codigo_cuenta_cxc,
            "id_asiento" => $idAsiento
        ]
    ]);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>