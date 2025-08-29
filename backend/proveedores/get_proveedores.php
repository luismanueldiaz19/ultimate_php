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

    // Filtro por nombre de proveedor
    $filters = [];
    $params = [];

    if (!empty($data['nombre_proveedor'])) {
        $params[] = '%' . $data['nombre_proveedor'] . '%';
        $filters[] = "nombre_proveedor ILIKE $" . count($params);
    }

    $whereClause = '';
    if (!empty($filters)) {
        $whereClause = "WHERE " . implode(" AND ", $filters);
    }

    // Consulta principal con paginación
    $sql = "
        SELECT 
            id_proveedor,
            nombre_proveedor,
            contacto_proveedor,
            telefono_proveedor,
            email_proveedor,
            direccion_proveedor,
            statu_proveedor,
            creado_en_proveedor,
            actualizado_en_proveedor
        FROM proveedores
        $whereClause
        ORDER BY id_proveedor ASC
        LIMIT $limit OFFSET $offset
    ";

    $result = pg_query_params($conn, $sql, $params);
    if (!$result) throw new Exception(pg_last_error($conn));

    $proveedores = pg_fetch_all($result) ?? [];

    // Total de registros para paginación
    $countSql = "SELECT COUNT(*) AS total FROM proveedores $whereClause";
    $countResult = pg_query_params($conn, $countSql, $params);
    $total = (int)pg_fetch_assoc($countResult)['total'];
    $totalPages = ceil($total / $limit);

    echo json_response([
        "success" => true,
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "total_pages" => $totalPages,
        "proveedores" => $proveedores
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ], 500);
}
?>

