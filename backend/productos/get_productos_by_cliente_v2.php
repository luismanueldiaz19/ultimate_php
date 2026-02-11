<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    $idCliente = $data['id_cliente'] ?? null;
    $page      = max(0, intval($data['page'] ?? 0));
    $limit     = min(100, max(10, intval($data['limit'] ?? 50)));
    $search    = trim($data['search'] ?? '');

    if (empty($idCliente)) {
        json_response([
            "success" => false,
            "message" => "Debes enviar el id_cliente"
        ], 400);
        exit;
    }

    pg_set_client_encoding($conn, "UTF8");

    $offset = $page * $limit;
    $params = [$idCliente];
    $where  = "WHERE p.statu = true";

    if ($search !== '') {
        $where .= " AND (
            p.codigo_producto ILIKE $" . (count($params) + 1) . " OR
            p.material ILIKE $" . (count($params) + 1) . " OR
            p.marca ILIKE $" . (count($params) + 1) . "
        )";
        $params[] = "%$search%";
    }

    $params[] = $limit;
    $params[] = $offset;

    $sql = "
        SELECT 
            p.id_producto,
            p.codigo_producto,
            p.material,
            p.marca,
            p.color,
            p.size,
            c.nombre_categoria AS categoria,
            productos_catalogos.ruta_imagen,
            inventario.stock_actual,
            CASE cl.tipo_precio
                WHEN 'two' THEN p.precio_two
                WHEN 'three' THEN p.precio_three
                ELSE p.precio_one
            END AS precio
        FROM productos p
        JOIN clientes cl ON cl.id_cliente = $1
        LEFT JOIN categorias c ON p.categoria_id = c.id_categoria
        LEFT JOIN inventario ON inventario.producto_id = p.id_producto
        LEFT JOIN productos_catalogos ON productos_catalogos.id_categoria = c.id_categoria
        $where
        ORDER BY p.id_producto DESC
        LIMIT $" . (count($params) - 1) . " OFFSET $" . count($params) . "
    ";

    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        throw new Exception(pg_last_error($conn));
    }

    $productos = pg_fetch_all($result) ?? [];

    json_response([
        "success"  => true,
        "page"     => $page,
        "limit"    => $limit,
        "hasMore"  => count($productos) === $limit,
        "productos"=> $productos
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    json_response([
        "success" => false,
        "message" => "Error inesperado: " . $e->getMessage()
    ], 500);
}
