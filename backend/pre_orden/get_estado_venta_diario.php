<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';
    header("Content-Type: application/json");

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $fields = [
        'date1' => $input['date1'] ?? '',
        'date2' => $input['date2'] ?? '',
    ];

    foreach ($fields as $key => $value) {
        $fields[$key] = pg_escape_string($conn, $value);
    }

    $required = ['date1', 'date2'];
    $empty_fields = [];

    foreach ($required as $field) {
        if (empty($fields[$field])) {
            $empty_fields[] = $field;
        }
    }

    if (!empty($empty_fields)) {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios incompletos',
            'empty_fields' => $empty_fields
        ]);
        exit;
    }

    $sql = "WITH ventas_por_dia AS (
  SELECT 
    DATE(creado_en) AS fecha,
    COUNT(DISTINCT num_orden) AS total_ordenes_vendidas,
    SUM(total_bruto) AS total_en_bruto,
    SUM(total_itbis) AS total_itbis_venta,
    SUM(total_final) AS total_final_venta
  FROM public.pre_orden
  WHERE DATE(creado_en) BETWEEN $1 AND $2
  GROUP BY DATE(creado_en)
),
cobros_por_dia AS (
  SELECT 
    DATE(fecha_pago) AS fecha,
    COUNT(DISTINCT pre_orden_id) AS total_pagos_realizados,
    SUM(monto_pago) AS total_cobro_ventas
  FROM public.pagos_pre_orden
  WHERE DATE(fecha_pago) BETWEEN $1 AND $2
  GROUP BY DATE(fecha_pago)
)

SELECT 
  COALESCE(v.fecha, c.fecha) AS fecha,
  COALESCE(v.total_ordenes_vendidas, 0) AS total_ordenes_vendidas,
  COALESCE(v.total_en_bruto, 0) AS total_en_bruto,
  COALESCE(v.total_itbis_venta, 0) AS total_itbis_venta,
  COALESCE(v.total_final_venta, 0) AS total_final_venta,
  COALESCE(c.total_pagos_realizados, 0) AS total_pagos_realizados,
  COALESCE(c.total_cobro_ventas, 0) AS total_cobro_ventas,
  COALESCE(v.total_final_venta, 0) - COALESCE(c.total_cobro_ventas, 0) AS diferencia
FROM ventas_por_dia v
FULL OUTER JOIN cobros_por_dia c ON v.fecha = c.fecha
ORDER BY fecha ASC;";

    $params = [$fields['date1'], $fields['date2']];

    try {
        $res = pg_query_params($conn, $sql, $params);
        $resumen = pg_fetch_all($res);

        echo json_encode([
            'success' => true,
            'data' => $resumen ?: []
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }

}
?>