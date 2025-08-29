<?php
include '../conexion.php';
include '../utils.php';

$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');

// Obtener ventas del mes con NCF válidos
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
WHERE 
  to_char(f.fecha, 'YYYY-MM') = $1
ORDER BY f.fecha ASC
";

$params = [ "$ano-$mes" ];
$res = pg_query_params($conn, $sql, $params);

if (!$res) {
  json_response(["error" => "Error en la consulta"], 500);
}

$ventas = pg_fetch_all($res) ?: [];

json_response([
  "formato" => "607",
  "ano" => $ano,
  "mes" => $mes,
  "cantidad" => count($ventas),
  "ventas" => $ventas
]);
