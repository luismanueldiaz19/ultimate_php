<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];
    $idCliente = $data['id_cliente'] ?? null;
    pg_set_client_encoding($conn, "UTF8");
    if (empty($idCliente)) {
        json_response([
            "success" => false,
            "message" => "Debes enviar el id_cliente"
        ], 400);
        exit;
    }
    $sql = "
        SELECT 
            p.id_producto,
            p.tela,
            p.nombre_producto,
            p.codigo_producto,
            c.nombre_categoria AS categoria,
            pr.nombre_proveedor AS proveedor,
            u.nombre_medida AS unidad_medida,
            i.nombre_impuesto AS impuesto,
            i.porcentaje_impuesto AS porcentaje_impuesto,
            a.nombre_almacen AS almacen,
            cc.nombre_contable AS cuenta_inventario,
            p.costo,
            p.precio_one,
            p.precio_two,
            p.precio_three,
            CASE cl.tipo_precio
                WHEN 'precio_one' THEN p.precio_one
                WHEN 'precio_two' THEN p.precio_two
                WHEN 'precio_three' THEN p.precio_three
                ELSE p.precio_one
            END AS precio_asignado,
            p.department,
            p.creado_en,
            p.productos_catalogos_id
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id_categoria
        LEFT JOIN proveedores pr ON p.proveedor_id = pr.id_proveedor
        LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id_unidad
        LEFT JOIN impuestos i ON p.impuesto_id = i.id_impuesto
        LEFT JOIN almacenes a ON p.almacen_id = a.id_almacen
        LEFT JOIN cuentas_contables cc ON p.cuenta_contable_id = cc.id_cuenta
        JOIN clientes cl ON cl.id_cliente = $1
    ";

    $result = pg_query_params($conn, $sql, [$idCliente]);
    if (!$result) {
        throw new Exception(pg_last_error($conn));
    }

    $productos = pg_fetch_all($result) ?? [];

    $productos = array_map(fn($row) => array_map('utf8_encode', $row), $productos);

    json_response([
        "success" => true,
        "total" => count($productos),
        "productos" => $productos
    ]);
} catch (Throwable $e) {
    json_response([
        "success" => false,
        "message" => "Error inesperado: " . $e->getMessage()
    ], 500);
}