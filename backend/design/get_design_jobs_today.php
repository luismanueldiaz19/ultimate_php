<?php

include '../conexion.php';
include '../utils.php';

header('Content-Type: application/json; charset=UTF-8');

try {

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    // 📅 Fechas requeridas
    $fecha_inicio = isset($data['fecha_inicio']) ? $data['fecha_inicio'] : null;
    $fecha_fin    = isset($data['fecha_fin']) ? $data['fecha_fin'] : null;

    if (!$fecha_inicio || !$fecha_fin) {
        json_response([
            'success' => false,
            'message' => 'Debe enviar fecha_inicio y fecha_fin'
        ], 400);
    }

    $query = '
       SELECT m.design_images_items_id,design_tipo.tipo_trabajo, m.design_tipo_id, 
m.comment_imagen, m.body_ubicacion, 
m.created_at_design_images, m.ruta, m.registed_by,
design_company.institution_name,design_company.cliente,
design_tipo.facturado_por, design_tipo.costo_logo, design_tipo.fecha_facturado,
design_tipo.has_cost,design_tipo.duracion
FROM public.design_images_items as m
inner join public.design_tipo ON design_tipo.design_tipo_id = m.design_tipo_id
inner join public.design_company ON design_company.design_company_id = design_tipo.design_company_id
WHERE DATE(m.created_at_design_images) BETWEEN $1 AND $2
ORDER BY DATE(m.created_at_design_images) DESC
    ';

    $result = pg_query_params($conn, $query, [$fecha_inicio, $fecha_fin]);

    if (!$result) {
        throw new Exception(pg_last_error($conn));
    }

    $items = pg_fetch_all($result) ?? [];

    // ✅ Si no hay registros
    if ($items === false) {
        json_response([
            'success' => false,
            'data'    => []
        ]);
    } else {

        json_response([
              'success' => true,
              'data'    => $items
          ]);

    }

} catch (Exception $e) {

    json_response([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
