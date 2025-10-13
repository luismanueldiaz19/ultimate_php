<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    // Leer parámetros JSON o POST
    $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
    // Validar y limpiar la lista de tipos de departamento
    $departamentosRaw = isset($data['depart']) ? trim($data['depart']) : '';
    $departamentos = array_filter(array_map('trim', explode(",", $departamentosRaw)));
    // Fechas por defecto si no se envían
    $fechaInicio = isset($data['date1']) ? trim($data['date1']) : '';


    // Validar formato de fecha (opcional)
    if (!strtotime($fechaInicio)) {
        json_response([
            "success" => false,
            "message" => "Formato de fecha inválido"
        ], 400);
    }

if (empty($departamentos)) {
    json_response([
        "success" => false,
        "message" => "La lista de tipos de departamento está vacía."
    ], 400);
}

// Construir placeholders seguros ($2, $3, ...)
$placeholders = [];
for ($i = 0; $i < count($departamentos); $i++) {
    $placeholders[] = '$' . ($i + 2); // $1 será la fecha
}
$placeholdersStr = implode(",", $placeholders);


    // Consulta SQL
    $sql = "
        SELECT 
            m.planificacion_work_id,
            departments.name_department,
            departments.path_image,
            departments.type,
            productos.codigo_producto,
            productos.nombre_producto,
            m.id_producto,
            m.item_pre_orden_id,
            m.department_id,
            m.estado_planificacion_work,
            m.work_creado_en,
            m.comentario_work,
            list_ficha_available.ficha,
            list_ficha_available.color_ficha,
            item_pre_orden.nota_producto,
            item_pre_orden.cant,
            item_pre_orden.tela,
            pre_orden.name_logo,
            pre_orden.num_orden,
            pre_orden.ficha_id,
            pre_orden.fecha_entrega,
            pre_orden.id_usuario,
            pre_orden.estado_hoja,
            clientes.nombre,
            clientes.rnc_cedula,
            clientes.tipo_entidad,
            clientes.tipo_identificacion,
            clientes.email,
            clientes.telefono,
            clientes.direccion,
            item_pre_orden.estado_item,
			item_pre_orden.design_tipo_id,
			design_tipo.tipo_trabajo,
			design_images_items.design_images_items_id,
			design_images_items.comment_imagen, 
			design_images_items.body_ubicacion,
			design_images_items.ruta
        FROM public.planificacion_work AS m
        INNER JOIN public.productos ON productos.id_producto = m.id_producto
        INNER JOIN public.item_pre_orden ON item_pre_orden.item_pre_orden_id = m.item_pre_orden_id
        INNER JOIN public.pre_orden ON pre_orden.pre_orden_id = item_pre_orden.pre_orden_id
        INNER JOIN public.clientes ON clientes.id_cliente = pre_orden.id_cliente
        INNER JOIN public.departments ON departments.department_id = m.department_id
        INNER JOIN public.list_ficha_available ON list_ficha_available.ficha_id = pre_orden.ficha_id
        INNER JOIN public.design_tipo ON design_tipo.design_tipo_id = item_pre_orden.design_tipo_id
		INNER JOIN public.design_images_items ON design_images_items.design_tipo_id = item_pre_orden.design_tipo_id
        WHERE pre_orden.fecha_entrega <= $1  AND departments.type IN ($placeholdersStr)
        AND m.estado_planificacion_work <> 'COMPLETADO'
        ORDER BY departments.name_department ASC";

    // Ejecutar consulta segura
    // $params = [$fechaInicio];
    $params = array_merge([$fechaInicio], $departamentos);


    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        throw new Exception("Error en la consulta: " . pg_last_error($conn));
    }

    $planificaciones = pg_fetch_all($result) ?? [];




   $agrupado = [];

foreach ($planificaciones as $row) {
    $workId = $row['planificacion_work_id'];
    $designId = $row['design_tipo_id'];

    // Inicializar agrupación por trabajo
    if (!isset($agrupado[$workId])) {


        $dataFichas = [
                'ficha_id' => $row['ficha_id'],
                'ficha' => $row['ficha'],
                'color_ficha' => json_decode($row['color_ficha'], true),
            ];
    $agrupado[$workId] = [
    "planificacion_work_id" => $workId,
    "name_department" => $row['name_department'],
    "path_image" => $row['path_image'],
    "type" => $row['type'],
    "codigo_producto" => $row['codigo_producto'],
    "nombre_producto" => $row['nombre_producto'],
    "id_producto" => $row['id_producto'],
    "item_pre_orden_id" => $row['item_pre_orden_id'],
    "department_id" => $row['department_id'],
    "estado_planificacion_work" => $row['estado_planificacion_work'],
    "work_creado_en" => $row['work_creado_en'],
    "comentario_work" => $row['comentario_work'],
     "Ficha" => $dataFichas,
    "nota_producto" => $row['nota_producto'],
    "cant" => $row['cant'],
    "tela" => $row['tela'],
    "name_logo" => $row['name_logo'],
    "num_orden" => $row['num_orden'],
    
    "fecha_entrega" => $row['fecha_entrega'],
    "id_usuario" => $row['id_usuario'],
    "estado_hoja" => $row['estado_hoja'],
    "nombre" => $row['nombre'],
    "rnc_cedula" => $row['rnc_cedula'],
    "tipo_entidad" => $row['tipo_entidad'],
    "tipo_identificacion" => $row['tipo_identificacion'],
    "email" => $row['email'],
    "telefono" => $row['telefono'],
    "direccion" => $row['direccion'],
    "estado_item" => $row['estado_item'], 
    "tipo_trabajo" => $row['tipo_trabajo'],           
    "designTipo" => []

]; 


}

    // Buscar si ya existe ese design_tipo_id dentro del trabajo
    $designsRef = &$agrupado[$workId]['designTipo'];
    
    $designIndex = array_search($designId, array_column($designsRef, 'design_tipo_id'));

    if ($designIndex === false) {
        // Si no existe, lo creamos
        $designsRef[] = [
            "design_tipo_id" => $designId,
            "tipo_trabajo" => $row['tipo_trabajo'],
            "designImagesItems" => []
        ];
        $designIndex = count($designsRef) - 1;
    }

    // Agregar imagen al grupo correspondiente
    $designsRef[$designIndex]['designImagesItems'][] = [
        "design_images_items_id" => $row['design_images_items_id'],
        "comment_imagen" => $row['comment_imagen'],
        "body_ubicacion" => $row['body_ubicacion'],
        "ruta" => $row['ruta']
    ];
}

// Reindexar para respuesta limpia
$agrupado = array_values($agrupado);






    json_response([
        "success" => true,
        "planificaciones" => $agrupado,
        "total" => count($agrupado)
    ]);
} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage()
    ], 500);
}