<?php
include '../conexion.php';

$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');

// Consultar facturas del mes
$sql = "
SELECT 
  c.rnc_cedula,
  CASE 
    WHEN c.tipo_identificacion = 'RNC' THEN 1
    WHEN c.tipo_identificacion = 'CEDULA' THEN 2
    ELSE 3
  END AS tipo_id,
  f.ncf,
  to_char(f.fecha, 'YYYY-MM-DD') AS fecha_comprobante,
  f.total_bruto,
  f.total_itbis,
  f.total_final,
  CASE 
    WHEN f.estado = 'ANULADA' THEN 2
    ELSE 1
  END AS estado
FROM facturas f
LEFT JOIN clientes c ON f.id_cliente = c.id_cliente
WHERE to_char(f.fecha, 'YYYY-MM') = $1
ORDER BY f.fecha ASC
";

$params = ["$ano-$mes"];
$res = pg_query_params($conn, $sql, $params);

$ventas = pg_fetch_all($res) ?: [];

header("Content-Type: text/plain");
header("Content-Disposition: attachment; filename=formato_607_{$ano}_{$mes}.txt");

foreach ($ventas as $v) {
  $linea = sprintf(
    "%-11s|%-1d|%-19s|%-10s|%012.2f|%012.2f|%012.2f|%1d\n",
    preg_replace('/[^0-9]/', '', $v['rnc_cedula']),  // quitar guiones
    $v['tipo_id'],
    $v['ncf'],
    $v['fecha_comprobante'],
    $v['total_bruto'],
    $v['total_itbis'],
    $v['total_final'],
    $v['estado']
  );
  echo $linea;
}
