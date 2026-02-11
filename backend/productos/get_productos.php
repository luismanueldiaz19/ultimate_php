<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // =========================
    // PAGINACIÓN
    // =========================
    $page  = max(1, intval($data['page'] ?? 1));
    $limit = min(100, max(20, intval($data['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    // =========================
    // FILTROS
    // =========================
    $search   = trim($data['search'] ?? '');
    $linea    = trim($data['linea'] ?? '');
    $material = trim($data['material'] ?? '');
    $estilo   = trim($data['estilo'] ?? '');
    $marca    = trim($data['marca'] ?? '');

    $where = [];
    $params = [];
    $i = 1;

    if ($search !== '') {
        $where[] = "(p.codigo_producto ILIKE $" . $i . "
                    OR p.material ILIKE $" . $i . "
                    OR p.marca ILIKE $" . $i . ")";
        $params[] = "%$search%";
        $i++;
    }

    if ($linea !== '') {
        $where[] = "p.linea = $" . $i;
        $params[] = $linea;
        $i++;
    }

    if ($material !== '') {
        $where[] = "p.material = $" . $i;
        $params[] = $material;
        $i++;
    }

    if ($estilo !== '') {
        $where[] = "p.estilo = $" . $i;
        $params[] = $estilo;
        $i++;
    }

    if ($marca !== '') {
        $where[] = "p.marca = $" . $i;
        $params[] = $marca;
        $i++;
    }

    $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // =========================
    // CONSULTA PRINCIPAL
    // =========================
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
        LEFT JOIN productos_catalogos ON productos_catalogos.id_categoria = c.id_categoria
        $whereSql
        ORDER BY p.id_producto DESC
        LIMIT $" . $i . " OFFSET $" . ($i + 1) . "
    ";

    $params[] = $limit;
    $params[] = $offset;

    $result = pg_query_params($conn, $sql, $params);
    if (!$result) {
        throw new Exception(pg_last_error($conn));
    }

    $productos = pg_fetch_all($result) ?? [];

    // =========================
    // TOTAL (para paginación)
    // =========================
    $countSql = "SELECT COUNT(*) FROM productos p $whereSql";
    $countParams = array_slice($params, 0, $i - 1);

    $countResult = pg_query_params($conn, $countSql, $countParams);
    $total = (int) pg_fetch_result($countResult, 0, 0);

    // =========================
    // RESPUESTA FINAL
    // =========================
    json_response([
        "success" => true,
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "total_pages" => ceil($total / $limit),
        "hasMore" => count($productos) === $limit,
        "productos" => $productos
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    json_response([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ], 500);
}
