<?php

include '../conexion.php';
include '../utils.php';

$data = json_decode(file_get_contents('php://input'), true);

$limit   = isset($data['limit']) ? intval($data['limit']) : 10;
$offset  = isset($data['offset']) ? intval($data['offset']) : 0;
$filtro  = isset($data['filtro']) ? trim($data['filtro']) : '';
$cliente = isset($data['cliente']) ? trim($data['cliente']) : '';

$where      = '';
$params     = [];
$paramIndex = 1;

if (!empty($filtro) && !empty($cliente)) {
    // Buscar por institution_name o cliente
    $where = "WHERE (dc.institution_name ILIKE '%' || $" . $paramIndex . " || '%' 
                  OR dc.cliente ILIKE '%' || $" . $paramIndex . " || '%')";
    $params[] = $filtro; // mismo valor para ambos
    $paramIndex++;
} elseif (!empty($filtro)) {
    // Solo filtro por institution_name
    $where    = "WHERE dc.institution_name ILIKE '%' || $" . $paramIndex . " || '%'";
    $params[] = $filtro;
    $paramIndex++;
} elseif (!empty($cliente)) {
    // Solo filtro por cliente
    $where    = "WHERE dc.cliente ILIKE '%' || $" . $paramIndex . " || '%'";
    $params[] = $cliente;
    $paramIndex++;
}

// Consulta con JOIN
$query = "
    SELECT 
        dc.design_company_id,
        dc.institution_name,
        dc.created_at AS company_created_at,
        dc.is_active,
        dc.registed_by, 
        dc.cliente,
		
        ea.nombre_estado AS estado_aprobacion,
		ea.descripcion  AS estado_descripcion,
        dt.design_tipo_id,
        dt.tipo_trabajo,
        dt.created_at AS tipo_created_at,
        dt.facturado_por,
        dt.costo_logo,
        dt.fecha_facturado,
        dt.has_cost, 
        dt.estado_aprobacion_id,

        di.design_images_items_id,
        di.comment_imagen,
        di.body_ubicacion,
        di.created_at_design_images,
        di.ruta

    FROM public.design_company dc
    LEFT JOIN public.design_tipo dt ON dt.design_company_id = dc.design_company_id
    LEFT JOIN public.design_images_items di ON di.design_tipo_id = dt.design_tipo_id
    LEFT JOIN estado_aprobacion ea ON dt.estado_aprobacion_id = ea.id
    $where
    ORDER BY dt.design_tipo_id DESC
    LIMIT $limit OFFSET $offset
";

// Conteo total
$countQuery = "SELECT COUNT(*) AS total FROM design_company dc $where";

$result      = pg_query_params($conn, $query, $params);
$countResult = pg_query_params($conn, $countQuery, $params);

$rows  = pg_fetch_all($result) ?? [];
$total = pg_fetch_result($countResult, 0, 'total');

// Agrupar por institución
$designs = [];
foreach ($rows as $row) {
    $companyId = $row['design_company_id'];

    if (!isset($designs[$companyId])) {
        $designs[$companyId] = [
            'design_company_id'   => $row['design_company_id'],
            'institution_name'    => $row['institution_name'],
            'created_at'          => $row['company_created_at'],
            'is_active'           => $row['is_active'],
            'registed_by'         => $row['registed_by'],
            'cliente'             => $row['cliente'],
            'designTipo'          => []
        ];
    }

    // Agrupar tipos de trabajo
    $tipoId = $row['design_tipo_id'];
    if ($tipoId) {
        if (!isset($designs[$companyId]['designTipo'][$tipoId])) {
            $designs[$companyId]['designTipo'][$tipoId] = [

                'design_tipo_id'     => $row['design_tipo_id'],
                'tipo_trabajo'       => $row['tipo_trabajo'],
                'created_at'         => $row['tipo_created_at'],
                'facturado_por'      => $row['facturado_por'],
                'costo_logo'         => $row['costo_logo'],
                'fecha_facturado'    => $row['fecha_facturado'],
                'has_cost'           => $row['has_cost'],
                'estado_aprobacion'  => $row['estado_aprobacion'],
                'estado_descripcion' => $row['estado_descripcion'],

                'estado_aprobacion_id' => $row['estado_aprobacion_id'],

                'designImagesItems' => []
            ];
        }

        // Agregar imagen si existe
        if (!empty($row['design_images_items_id'])) {
            $designs[$companyId]['designTipo'][$tipoId]['designImagesItems'][] = [
                'design_images_items_id'   => $row['design_images_items_id'],
                'comment_imagen'           => $row['comment_imagen'],
                'body_ubicacion'           => $row['body_ubicacion'],
                'created_at_design_images' => $row['created_at_design_images'],
                'ruta'                     => $row['ruta']
            ];
        }
    }
}

// Reindexar para respuesta limpia
$designs = array_values(array_map(function ($company) {
    $company['designTipo'] = array_values($company['designTipo']);
    return $company;
}, $designs));

json_response([
    'success' => true,
    'designs' => $designs,
    'total'   => intval($total),
]);
