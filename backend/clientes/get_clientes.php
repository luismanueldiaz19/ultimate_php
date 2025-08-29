<?php
include '../conexion.php';
include '../utils.php';

$data = json_decode(file_get_contents("php://input"), true);

$limit = isset($data['limit']) ? intval($data['limit']) : 10;
$offset = isset($data['offset']) ? intval($data['offset']) : 0;
$filtro = isset($data['filtro']) ? trim($data['filtro']) : '';

// Filtro por nombre si se proporciona
$where = '';
$params = [];
if (!empty($filtro)) {
    $where = "WHERE nombre ILIKE '%' || $1 || '%'";
    $params[] = $filtro;
}

// Consulta principal
$query = "
    SELECT id_cliente, nombre, rnc_cedula, tipo_entidad, tipo_identificacion, email, telefono, direccion, creado_en
    FROM clientes
    $where
    ORDER BY creado_en DESC
    LIMIT $limit OFFSET $offset
";

// Conteo total
$countQuery = "SELECT COUNT(*) AS total FROM clientes $where";

$result = pg_query_params($conn, $query, $params);
$countResult = pg_query_params($conn, $countQuery, $params);

$clientes = pg_fetch_all($result) ?? [];
$total = pg_fetch_result($countResult, 0, "total");

json_response([
    'success' => true,
    'clientes' => $clientes,
    'total' => intval($total),
]);
