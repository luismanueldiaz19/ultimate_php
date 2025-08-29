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

    // Filtros opcionales
    $filters = [];
    $params = [];

    if (!empty($data['nombre_producto'])) {
        $params[] = '%' . $data['nombre_producto'] . '%';
        $filters[] = "p.nombre_producto ILIKE $" . count($params);
    }
    if (!empty($data['categoria'])) {
        $params[] = '%' . $data['categoria'] . '%';
        $filters[] = "c.nombre_categoria ILIKE $" . count($params);
    }
    if (!empty($data['proveedor'])) {
        $params[] = '%' . $data['proveedor'] . '%';
        $filters[] = "pr.nombre_proveedor ILIKE $" . count($params);
    }

     if (!empty($data['codigo_producto'])) {
        $params[] = '%' . $data['codigo_producto'] . '%';
        $filters[] = "p.codigo_producto ILIKE $" . count($params);
    }

    $whereClause = '';
    if (!empty($filters)) {
        $whereClause = "WHERE " . implode(" AND ", $filters);
    }

    // Consulta principal con paginación
    $sql = "
        SELECT 
            p.id_producto,
            p.nombre_producto,
            p.descripcion,
            p.codigo_producto,
            c.nombre_categoria AS categoria,
            pr.nombre_proveedor AS proveedor,
            u.nombre_medida AS unidad_medida,
            i.nombre_impuesto AS impuesto,
            i.porcentaje_impuesto  AS porcentaje_impuesto,
            a.nombre_almacen AS almacen,
            cc.nombre_contable AS cuenta_inventario,
            p.precio_compra,
            p.precio_venta,
            p.stock_actual,
            p.stock_minimo,
            p.activo,
            p.creado_en,
            p.actualizado_en
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id_categoria
        LEFT JOIN proveedores pr ON p.proveedor_id = pr.id_proveedor
        LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id_unidad
        LEFT JOIN impuestos i ON p.impuesto_id = i.id_impuesto
        LEFT JOIN almacenes a ON p.almacen_id = a.id_almacen
        LEFT JOIN cuentas_contables cc ON p.cuenta_contable_id = cc.id_cuenta
        $whereClause
        ORDER BY p.id_producto
        LIMIT $limit OFFSET $offset
    ";

    $result = pg_query_params($conn, $sql, $params);
    if (!$result) {
        throw new Exception(pg_last_error($conn));
    }

    $productos = pg_fetch_all($result) ?? [];

    // Obtener total de registros para paginación
    $countSql = "SELECT COUNT(*) AS total FROM productos p
                 LEFT JOIN categorias c ON p.categoria_id = c.id_categoria
                 LEFT JOIN proveedores pr ON p.proveedor_id = pr.id_proveedor
                 $whereClause";
    $countResult = pg_query_params($conn, $countSql, $params);
    $total = (int)pg_fetch_assoc($countResult)['total'];
    $totalPages = ceil($total / $limit);

    echo json_response([
        "success" => true,
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "total_pages" => $totalPages,
        "productos" => $productos
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ], 500);
}
?>
