<?php
include '../conexion.php';
include '../utils.php';

try {
    // Consulta principal sin filtros ni paginación
    $query = "
        SELECT 
  c.id_cliente,
  c.telefono,
  c.nombre,
  c.rnc_cedula,
  po.pre_orden_id,
  po.num_orden,
  po.total_final AS monto_facturado,
  COALESCE(SUM(pp.monto_pago), 0) AS monto_pagado,
  (po.total_final - COALESCE(SUM(pp.monto_pago), 0)) AS monto_pendiente,
  po.fecha_entrega,
  po.estado_general,
  po.is_facturado
FROM public.pre_orden po
JOIN public.clientes c ON po.id_cliente = c.id_cliente
LEFT JOIN public.pagos_pre_orden pp ON po.pre_orden_id = pp.pre_orden_id
GROUP BY 
  c.id_cliente, c.nombre, c.rnc_cedula,
  po.pre_orden_id, po.num_orden, po.total_final,
  po.fecha_entrega, po.estado_general, po.is_facturado
HAVING (po.total_final - COALESCE(SUM(pp.monto_pago), 0)) > 0 AND po.num_orden ~ '^ORD-[0-9]+$'
ORDER BY c.nombre, po.fecha_entrega DESC;
    ";

    // Conteo total de órdenes únicas
    $countQuery = "
    
     SELECT COUNT(*) AS total_pendientes
FROM (
  SELECT 
    po.pre_orden_id
  FROM public.pre_orden po
  JOIN public.clientes c ON po.id_cliente = c.id_cliente
  LEFT JOIN public.pagos_pre_orden pp ON po.pre_orden_id = pp.pre_orden_id
  GROUP BY 
    c.id_cliente, c.nombre, c.rnc_cedula,
    po.pre_orden_id, po.num_orden, po.total_final,
    po.fecha_entrega, po.estado_general, po.is_facturado
  HAVING (po.total_final - COALESCE(SUM(pp.monto_pago), 0)) > 0 and po.num_orden ~ '^ORD-[0-9]+$'
) AS sub;  
    ";

    $result = pg_query($conn, $query);
    $countResult = pg_query($conn, $countQuery);

    if (!$result || !$countResult) {
        throw new Exception("Error al ejecutar las consultas.");
    }

    $cuentas = pg_fetch_all($result) ?? [];
    $total = pg_fetch_result($countResult, 0, "total_pendientes");

    json_response([
        'success' => true,
        'cuentas' => $cuentas,
        'total' => intval($total),
    ]);

} catch (Exception $e) {
    json_response([
        'success' => false,
        'message' => 'Ocurrió un error al obtener las cuentas por cobrar.',
        'error' => $e->getMessage(),
    ], 500);
}