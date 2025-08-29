<?php
include '../conexion.php';
include '../utils.php';

$data = json_decode(file_get_contents("php://input"), true);

$limit = isset($data['limit']) ? intval($data['limit']) : 10;
$offset = isset($data['offset']) ? intval($data['offset']) : 0;
$filtro = isset($data['filtro']) ? trim($data['filtro']) : '';

$where = '';
$params = [];
if (!empty($filtro)) {
    $where = "WHERE institution_name ILIKE '%' || $1 || '%'";
    $params[] = $filtro;
}

// Consulta principal de diseños
$query = "
    SELECT 
        design_jobs_id, 
        institution_name, 
        job_type, 
        version, 
        created_at, 
        updated_at, 
        is_active, 
        notes,
        color_scheme,
        pantones
    FROM design_jobs
    $where
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
";

// Conteo total
$countQuery = "SELECT COUNT(*) AS total FROM design_jobs $where";

$result = pg_query_params($conn, $query, $params);

$countResult = pg_query_params($conn, $countQuery, $params);

$designs = pg_fetch_all($result) ?? [];

$total = pg_fetch_result($countResult, 0, "total");

// 🔁 Agregar imágenes relacionadas por diseño
foreach ($designs as &$design) {
    $designId = $design['design_jobs_id'];
    $imgQuery = "
        SELECT 
            design_image_id, 
            design_jobs_id, 
            image_file, 
            location, 
            created_at, 
            updated_at
        FROM design_images
        WHERE design_jobs_id = $1
        ORDER BY created_at ASC
    ";
    $imgResult = pg_query_params($conn, $imgQuery, [$designId]);
    $design['DesignImage'] = pg_fetch_all($imgResult) ?? [];
}

json_response([
    'success' => true,
    'designs' => $designs,
    'total' => intval($total),
]);
