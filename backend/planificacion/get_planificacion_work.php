<?php

include '../conexion.php';
include '../utils.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

try {

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    // ===============================
    // FECHA (opcional)
    // ===============================
    $fecha = isset($data['fecha']) && !empty($data['fecha'])
        ? $data['fecha']
        : date('Y-m-d'); // si no envían fecha usa la de hoy

    // ===============================
    // CONSULTA
    // ===============================
    $sql = "
        SELECT 
            i.item_pre_orden_id,
            i.pre_orden_id,
            i.id_producto,
            i.nota_producto,
            i.precio,
            i.itbs,
            i.cant,
            i.estado_item,
            i.creado_en AS creado_item,
            i.is_produccion,
            i.design_tipo_id,

            concat_ws(' ',
                nullif(nullif(upper(trim(productos.linea)), 'NULL'), 'N/A'),
                nullif(nullif(upper(trim(productos.marca)), 'NULL'), 'N/A'),
                nullif(nullif(upper(trim(productos.estilo)), 'NULL'), 'N/A'),
                nullif(nullif(upper(trim(productos.material)), 'NULL'), 'N/A'),
                nullif(nullif(upper(trim(productos.genero)), 'NULL'), 'N/A'),
                nullif(nullif(upper(trim(productos.color)), 'NULL'), 'N/A'),
                nullif(nullif(upper(trim(productos.size)), 'NULL'), 'N/A')
    ) AS nombre_producto,

            p.num_orden,
            p.id_cliente,
            p.estado_hoja,
            p.estado_general,
            p.fecha_entrega,
            p.nota_orden,
            p.name_logo,
            p.num_comprobante,
            p.fecha_emision,

            f.ficha_id,
            f.ficha,
            f.color_ficha,

            dt.design_tipo_id AS design_id,
            dt.tipo_trabajo,

            di.design_images_items_id,
            di.comment_imagen,
            di.body_ubicacion,
            di.created_at_design_images,
            di.ruta,

            clientes.nombre,
	        clientes.rnc_cedula,
	        clientes.telefono,
	        clientes.direccion

        FROM public.item_pre_orden i

        INNER JOIN public.pre_orden p 
            ON p.pre_orden_id = i.pre_orden_id

        INNER JOIN public.list_ficha_available f
            ON f.ficha_id = p.ficha_id

            -- Producto de la orden
        INNER JOIN public.productos ON productos.id_producto = i.id_producto

        INNER JOIN public.clientes ON clientes.id_cliente = p.id_cliente

        LEFT JOIN public.design_tipo dt
            ON dt.design_tipo_id = i.design_tipo_id

        LEFT JOIN public.design_images_items di
            ON di.design_tipo_id = dt.design_tipo_id

        WHERE  p.estado_general NOT IN ('ENTREGADO', 'POR ENTREGAR')
          AND  i.estado_item = 'PENDIENTE'
          AND  p.fecha_entrega::date <= $1::date

        ORDER BY p.num_orden ASC, i.item_pre_orden_id ASC
    ";

    $result = pg_query_params($conn, $sql, [$fecha]);

    if (!$result) {
        throw new Exception(pg_last_error($conn));
    }

    $planificaciones = pg_fetch_all($result) ?? [];

    /* ===============================
       AGRUPACIÓN AQUÍ 👇
    ================================ */

    $agrupado = [];

    foreach ($planificaciones as $row) {

        $itemId   = $row['item_pre_orden_id'];

        if (!isset($agrupado[$itemId])) {

            $agrupado[$itemId] = [
                'item_pre_orden_id' => $itemId,
                'pre_orden_id'      => $row['pre_orden_id'],
                'id_producto'       => $row['id_producto'],
                'estado_hoja'       => $row['estado_hoja'],
                'nota_producto'     => $row['nota_producto'],
                'precio'            => $row['precio'],
                'itbs'              => $row['itbs'],
                'cant'              => $row['cant'],
                'estado_item'       => $row['estado_item'],
                'fecha_entrega'     => $row['fecha_entrega'],
                'num_orden'         => $row['num_orden'],
                'name_logo'         => $row['name_logo'],
                'Ficha'             => [
                    'ficha_id'    => $row['ficha_id'],
                    'ficha'       => $row['ficha'],
                    'color_ficha' => json_decode($row['color_ficha'], true),
                ],
                'Cliente'             => [
                     'id_cliente'         => $row['id_cliente'],
                     'nombre'             => $row['nombre'],
                     'rnc_cedula'         => $row['rnc_cedula'],
                     'telefono'           => $row['telefono'],
                     'direccion'          => $row['direccion'],
                ],
                'Producto'             => [
                    'nombre_producto'    => $row['nombre_producto']
                ],
                'DesignTipo' => null
            ];
        }

        $designId = $row['design_tipo_id'];

        if (!empty($designId)) {

            // Si aún no se ha creado el objeto designTipo
            if ($agrupado[$itemId]['DesignTipo'] === null) {

                $agrupado[$itemId]['DesignTipo'] = [
                    'design_tipo_id'    => $designId,
                    'tipo_trabajo'      => $row['tipo_trabajo'],
                    'designImagesItems' => []
                ];
            }

            // Agregar imagen si existe
            if (!empty($row['design_images_items_id'])) {
                $agrupado[$itemId]['DesignTipo']['designImagesItems'][] = [
                    'design_images_items_id' => $row['design_images_items_id'],
                    'comment_imagen'         => $row['comment_imagen'],
                    'body_ubicacion'         => $row['body_ubicacion'],
                    'ruta'                   => $row['ruta']
                ];
            }
        }

    }

    $agrupado = array_values($agrupado);

    /* ===============================
       RESPUESTA FINAL
    ================================ */

    json_response([
        'success' => true,
        'data'    => $agrupado
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
