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

    // Filtro por nombre de categoría
    $filters = [];
    $params = [];

    if (!empty($data['nombre_categoria'])) {
        $params[] = '%' . $data['nombre_categoria'] . '%';
        $filters[] = "nombre_categoria ILIKE $" . count($params);
    }

    $whereClause = '';
    if (!empty($filters)) {
        $whereClause = "WHERE " . implode(" AND ", $filters);
    }

    // Consulta principal con paginación
    $sql = "
        SELECT 
            id_categoria,
            nombre_categoria,
            descripcion_categoria,
            abreviado,
            secuencia,
            status_categoria,
            creado_en,
            actualizado_en
        FROM categorias
        $whereClause
        ORDER BY id_categoria ASC
        LIMIT $limit OFFSET $offset
    ";

    $result = pg_query_params($conn, $sql, $params);
    if (!$result) throw new Exception(pg_last_error($conn));

    $categorias = pg_fetch_all($result) ?? [];

    // Total de registros para paginación
    $countSql = "SELECT COUNT(*) AS total FROM categorias $whereClause";
    $countResult = pg_query_params($conn, $countSql, $params);
    $total = (int)pg_fetch_assoc($countResult)['total'];
    $totalPages = ceil($total / $limit);

    echo json_response([
        "success" => true,
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "total_pages" => $totalPages,
        "categorias" => $categorias
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ], 500);
}
?>