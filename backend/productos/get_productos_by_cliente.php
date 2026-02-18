<?php

include '../conexion.php';
include '../utils.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

try {
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $idCliente = $data['id_cliente']                                 ?? null;

    pg_set_client_encoding($conn, 'UTF8');

    if (empty($idCliente)) {
        json_response([
            'success' => false,
            'message' => 'Debes enviar el id_cliente'
        ], 400);
        exit;
    }

    $sql = "
        SELECT 
            p.id_producto,
            p.codigo_producto,

            -- Nombre dinámico del producto
            concat_ws(' ',
                nullif(nullif(upper(trim(p.linea)), 'NULL'), 'N/A'),
                nullif(nullif(upper(trim(p.marca)), 'NULL'), 'N/A'),
                nullif(nullif(upper(trim(p.estilo)), 'NULL'), 'N/A'),
                nullif(nullif(upper(trim(p.material)), 'NULL'), 'N/A'),
                nullif(nullif(upper(trim(p.genero)), 'NULL'), 'N/A'),
                nullif(nullif(upper(trim(p.color)), 'NULL'), 'N/A'),
                nullif(nullif(upper(trim(p.size)), 'NULL'), 'N/A')
            ) AS nombre_producto,

            p.statu,
            p.department,

            u.nombre_medida AS unidad_medida,
            i.porcentaje_impuesto AS porcentaje_impuesto,

            -- 💰 Precio según cliente
            CASE cl.tipo_precio
                WHEN 'two' THEN p.precio_two
                WHEN 'three' THEN p.precio_three
                ELSE p.precio_one
            END AS precio_asignado,

            inventario.stock_actual,
            inventario.reserva,

            p.productos_catalogos_id

        FROM productos p
        LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id_unidad
        LEFT JOIN impuestos i ON p.impuesto_id = i.id_impuesto
        LEFT JOIN inventario ON inventario.producto_id = p.id_producto
        JOIN clientes cl ON cl.id_cliente = $1
    ";

    $result = pg_query_params($conn, $sql, [$idCliente]);

    if (!$result) {
        throw new Exception(pg_last_error($conn));
    }

    $productos = pg_fetch_all($result) ?? [];

    json_response([
        'success'   => true,
        'total'     => count($productos),
        'productos' => $productos
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Error inesperado: ' . $e->getMessage()
    ], 500);
}
