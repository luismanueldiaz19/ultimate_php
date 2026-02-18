<?php

require_once('../conexion.php');
require_once('../utils.php');

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// -----------------------------
// Paginación
// -----------------------------

$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];

// --------------------------------
// Paginación
// --------------------------------
$per_page = isset($input['per_page']) ? (int)$input['per_page'] : 10;
$page     = isset($input['page']) ? (int)$input['page'] : 1;
$page     = max($page, 1);
$offset   = ($page - 1) * $per_page;

// --------------------------------
// Filtro de búsqueda
// --------------------------------
$search = isset($input['search']) ? trim($input['search']) : '';

// Expresión del nombre completo (reutilizable)
$nombre_producto_expr = "
concat_ws(' ',
    nullif(nullif(upper(trim(p.linea)), 'NULL'), 'N/A'),
    nullif(nullif(upper(trim(p.marca)), 'NULL'), 'N/A'),
    nullif(nullif(upper(trim(p.estilo)), 'NULL'), 'N/A'),
    nullif(nullif(upper(trim(p.material)), 'NULL'), 'N/A'),
    nullif(nullif(upper(trim(p.genero)), 'NULL'), 'N/A'),
    nullif(nullif(upper(trim(p.color)), 'NULL'), 'N/A'),
    nullif(nullif(upper(trim(p.size)), 'NULL'), 'N/A')
)
";

// WHERE dinámico
$where = '';
if ($search !== '') {
    $safe_search = pg_escape_string($conn, strtoupper($search));
    $where       = "WHERE $nombre_producto_expr ILIKE '%$safe_search%'";
}

// -----------------------------
// Conteo total
// -----------------------------
$count_query = "
SELECT COUNT(*) AS total
FROM productos p
LEFT JOIN categorias c ON p.categoria_id = c.id_categoria
LEFT JOIN proveedores pr ON p.proveedor_id = pr.id_proveedor
LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id_unidad
LEFT JOIN impuestos i ON p.impuesto_id = i.id_impuesto
LEFT JOIN almacenes a ON p.almacen_id = a.id_almacen
LEFT JOIN inventario inv ON inv.producto_id = p.id_producto
$where
";

$count_result = pg_query($conn, $count_query);
if (!$count_result) {
    echo json_encode(['error' => pg_last_error()]);
    exit;
}

$total       = (int)pg_fetch_result($count_result, 0, 'total');
$total_pages = ($per_page > 0) ? ceil($total / $per_page) : 1;

// -----------------------------
// Consulta principal
// -----------------------------
$query = "
SELECT
    p.id_producto,
    p.codigo_producto,

    $nombre_producto_expr AS nombre_producto,

    p.linea,
    p.material,
    p.estilo,
    p.marca,
    p.genero,
    p.color,
    p.size,
    p.statu,
    p.color_hex,

    c.nombre_categoria AS categoria,
    pr.nombre_proveedor AS proveedor,
    u.nombre_medida AS unidad_medida,
    i.nombre_impuesto AS impuesto,
    i.porcentaje_impuesto AS porcentaje_impuesto,
    a.nombre_almacen AS almacen,

    p.costo,
    p.precio_one,
    p.precio_two,
    p.precio_three,
    p.department,

    inv.stock_actual,
    inv.reserva,

    p.creado_en,
    p.productos_catalogos_id

FROM productos p
LEFT JOIN categorias c ON p.categoria_id = c.id_categoria
LEFT JOIN proveedores pr ON p.proveedor_id = pr.id_proveedor
LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id_unidad
LEFT JOIN impuestos i ON p.impuesto_id = i.id_impuesto
LEFT JOIN almacenes a ON p.almacen_id = a.id_almacen
LEFT JOIN inventario inv ON inv.producto_id = p.id_producto

$where
ORDER BY nombre_producto  DESC
LIMIT $per_page OFFSET $offset
";

$result = pg_query($conn, $query);
if (!$result) {
    echo json_encode(['error' => pg_last_error()]);
    exit;
}

// -----------------------------
// Resultado
// -----------------------------
$data = [];
while ($row = pg_fetch_assoc($result)) {
    $data[] = $row;
}

$response = [
    'total'        => $total,
    'per_page'     => $per_page,
    'current_page' => $page,
    'total_pages'  => $total_pages,
    'data'         => $data,
];

echo json_encode($response);
pg_close($conn);
