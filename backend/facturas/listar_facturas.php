<?php
include '../conexion.php';
include '../utils.php';

$fechaInicio = $_GET['fecha_inicio'] ?? null;
$fechaFin = $_GET['fecha_fin'] ?? null;
$estado = $_GET['estado'] ?? null;
$idCliente = $_GET['id_cliente'] ?? null;
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$limit = isset($_GET['limit']) ? max((int)$_GET['limit'], 1) : 20;
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];
$paramIdx = 1;

if ($fechaInicio) {
  $whereClauses[] = "fecha >= $" . $paramIdx++;
  $params[] = $fechaInicio . " 00:00:00";
}
if ($fechaFin) {
  $whereClauses[] = "fecha <= $" . $paramIdx++;
  $params[] = $fechaFin . " 23:59:59.999999";
}

if ($estado) {
  $whereClauses[] = "estado = $" . $paramIdx++;
  $params[] = $estado;
}
if ($idCliente) {
  $whereClauses[] = "id_cliente = $" . $paramIdx++;
  $params[] = $idCliente;
}

$whereSql = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";

$sql = "
  SELECT f.*, c.nombre AS cliente_nombre
  FROM facturas f
  LEFT JOIN clientes c ON f.id_cliente = c.id_cliente
  $whereSql
  ORDER BY fecha DESC
  LIMIT $limit OFFSET $offset
";

$res = pg_query_params($conn, $sql, $params);

if (!$res) {
  json_response(["error" => "Error en la consulta"], 500);
}

$facturas = pg_fetch_all($res) ?: [];

json_response([
  "page" => $page,
  "limit" => $limit,
  "count" => count($facturas),
  "facturas" => $facturas
]);
