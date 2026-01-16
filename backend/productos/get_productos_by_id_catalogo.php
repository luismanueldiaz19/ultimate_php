<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

function parsePgArray($text) {
    $text = trim($text, '{}');
    $items = str_getcsv($text); // Maneja comillas y comas correctamente
    return array_map('trim', $items);
}

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Paginación
    $page = isset($data['page']) && is_numeric($data['page']) ? (int)$data['page'] : 1;
    $limit = isset($data['limit']) && is_numeric($data['limit']) ? (int)$data['limit'] : 100;
    $offset = ($page - 1) * $limit;

    // Filtro por productos_catalogos_id
    $catalogoId = isset($data['productos_catalogos_id']) && is_numeric($data['productos_catalogos_id']) 
        ? (int)$data['productos_catalogos_id'] 
        : null;

    // Consulta principal con paginación y filtro opcional
    $sql = "
        SELECT 
            p.id_producto,
            p.codigo_producto,
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
            inventario.stock_actual,
            inventario.reserva,
            productos_catalogos.ruta_imagen,
            p.creado_en,
            p.productos_catalogos_id
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id_categoria
        LEFT JOIN proveedores pr ON p.proveedor_id = pr.id_proveedor
        LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id_unidad
        LEFT JOIN impuestos i ON p.impuesto_id = i.id_impuesto
        LEFT JOIN almacenes a ON p.almacen_id = a.id_almacen
        LEFT JOIN inventario ON inventario.producto_id = p.id_producto
        LEFT JOIN public.productos_catalogos ON productos_catalogos.id_categoria = c.id_categoria
        " . ($catalogoId !== null ? "WHERE p.productos_catalogos_id = $catalogoId" : "") . "
        ORDER BY p.id_producto
        LIMIT $limit OFFSET $offset
    ";

    $result = pg_query($conn, $sql);
    if (!$result) {
        throw new Exception(pg_last_error($conn));
    }

    $productos = pg_fetch_all($result) ?? [];

    // Total de productos (con filtro si aplica)
    $countSql = "SELECT COUNT(*) AS total FROM productos" . ($catalogoId !== null ? " WHERE productos_catalogos_id = $catalogoId" : "");
    $countResult = pg_query($conn, $countSql);
    $total = (int)pg_fetch_assoc($countResult)['total'];
    $totalPages = ceil($total / $limit);

    // Agrupaciones visuales
    $groupSql = "SELECT 
        ARRAY(
            SELECT DISTINCT linea 
            FROM productos 
            WHERE linea IS NOT NULL AND linea <> '' 
            ORDER BY linea
        ) AS lineas,
        ARRAY(
            SELECT DISTINCT material 
            FROM productos 
            WHERE material IS NOT NULL AND material <> '' 
            ORDER BY material
        ) AS materiales,
        ARRAY(
            SELECT DISTINCT estilo 
            FROM productos 
            WHERE estilo IS NOT NULL AND estilo <> '' 
            ORDER BY estilo
        ) AS estilos,
        ARRAY(
            SELECT DISTINCT genero 
            FROM productos 
            WHERE genero IS NOT NULL AND genero <> '' 
            ORDER BY genero
        ) AS genero,
        ARRAY(
            SELECT DISTINCT color 
            FROM productos 
            WHERE color IS NOT NULL AND color <> '' 
            ORDER BY color
        ) AS color,
        ARRAY(
            SELECT DISTINCT marca 
            FROM productos 
            WHERE marca IS NOT NULL AND marca <> '' 
            ORDER BY marca
        ) AS marcas";

    $groupResult = pg_query($conn, $groupSql);
    $groupData = pg_fetch_assoc($groupResult);

    echo json_response([
        "success" => true,
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "total_pages" => $totalPages,
        "lineas" => isset($groupData['lineas']) ? parsePgArray($groupData['lineas']) : [],
        "materiales" => isset($groupData['materiales']) ? parsePgArray($groupData['materiales']) : [],
        "estilos" => isset($groupData['estilos']) ? parsePgArray($groupData['estilos']) : [],
        "marcas" => isset($groupData['marcas']) ? parsePgArray($groupData['marcas']) : [],
        "color" => isset($groupData['color']) ? parsePgArray($groupData['color']) : [],
        "genero" => isset($groupData['genero']) ? parsePgArray($groupData['genero']) : [],
        "productos" => $productos,
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ], 500);
}
?>