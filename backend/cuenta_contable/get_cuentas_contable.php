<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Parámetros de paginación
    $page = isset($data['page']) && is_numeric($data['page']) ? (int)$data['page'] : 1;
    $limit = isset($data['limit']) && is_numeric($data['limit']) ? (int)$data['limit'] : 20;
    $offset = ($page - 1) * $limit;

    // Filtros dinámicos
    $filters = [];
    $params = [];

    if (!empty($data['nombre_contable'])) {
        $params[] = '%' . $data['nombre_contable'] . '%';
        $filters[] = "nombre_contable ILIKE $" . count($params);
    }

    if (!empty($data['codigo_contable'])) {
        $params[] = '%' . $data['codigo_contable'] . '%';
        $filters[] = "codigo_contable ILIKE $" . count($params);
    }

    $whereClause = '';
    if (!empty($filters)) {
        $whereClause = "WHERE " . implode(" AND ", $filters);
    }

    // Consulta principal
    $sql = "
        SELECT 
            id_cuenta,
            codigo_contable,
            nombre_contable,
            tipo_cuenta_contable,
            statu_contable,
            creado_en_contable,
            actualizado_en_contable
        FROM public.cuentas_contables
        $whereClause
        ORDER BY id_cuenta ASC
        LIMIT $limit OFFSET $offset
    ";

    $result = pg_query_params($conn, $sql, $params);
    if (!$result) throw new Exception(pg_last_error($conn));

    $cuentas = pg_fetch_all($result) ?? [];

    // Conteo total para paginación
    $countSql = "SELECT COUNT(*) AS total FROM public.cuentas_contables $whereClause";
    $countResult = pg_query_params($conn, $countSql, $params);
    $total = (int)pg_fetch_assoc($countResult)['total'];
    $totalPages = ceil($total / $limit);

    echo json_response([
        "success" => true,
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "total_pages" => $totalPages,
        "cuentas" => $cuentas
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ], 500);
}
?>