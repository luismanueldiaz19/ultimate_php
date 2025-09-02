<?php
include '../conexion.php';

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true) ?? [];

// Parámetros de paginación
$limit  = isset($data['limit'])  ? intval($data['limit'])  : 10;
$offset = isset($data['offset']) ? intval($data['offset']) : 0;

// Parámetro de filtro (puede ser ficha o número de orden)
$filtro = isset($data['filtro']) ? trim($data['filtro']) : '';

// Base de consulta
$sql = "
   SELECT 
    p.pre_orden_id,
    p.ficha_id,
    p.num_orden,
    p.id_usuario,
    p.id_cliente,
    p.estado_hoja,
    p.estado_general,
    p.creado_en,
    p.fecha_entrega,
    p.total_bruto,
    p.total_itbis,
    p.total_final,
    p.is_facturado,
    p.name_logo,
    i.item_pre_orden_id,
    i.design_image_id,
    i.id_producto,
    i.nota_producto,
    i.precio,
    i.itbs,
    i.cant,
    i.estado_item,
    i.creado_en AS creado_item
FROM public.pre_orden p
LEFT JOIN public.item_pre_orden i 
       ON p.pre_orden_id = i.pre_orden_id
WHERE p.estado_general != 'Entregado'
";

// Si hay filtro, lo aplicamos
$params = [];
if (!empty($filtro)) {
    $sql .= " AND (CAST(p.ficha_id AS TEXT) ILIKE $1 OR p.num_orden ILIKE $1) ";
    $params[] = "%$filtro%";
}

// Orden y paginación
$sql .= " ORDER BY p.creado_en DESC LIMIT $limit OFFSET $offset";

try {
    if (!empty($params)) {
        $res = pg_query_params($conexion, $sql, $params);
    } else {
        $res = pg_query($conexion, $sql);
    }

    $ordenes = pg_fetch_all($res);

    echo json_encode([
        "success" => true,
        "data" => $ordenes ?: [],
        "limit" => $limit,
        "offset" => $offset
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
