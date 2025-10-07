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
            design_images.comment_imagen,
            design_images.body_ubicacion, 
            design_images.tipo_trabajo,
            design_images.ruta
        FROM public.planificacion_work AS m
        INNER JOIN public.productos ON productos.id_producto = m.id_producto
        INNER JOIN public.item_pre_orden ON item_pre_orden.item_pre_orden_id = m.item_pre_orden_id
        INNER JOIN public.pre_orden ON pre_orden.pre_orden_id = item_pre_orden.pre_orden_id
        INNER JOIN public.clientes ON clientes.id_cliente = pre_orden.id_cliente
        INNER JOIN public.departments ON departments.department_id = m.department_id
        INNER JOIN public.list_ficha_available ON list_ficha_available.ficha_id = pre_orden.ficha_id
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

    json_response([
        "success" => true,
        "planificaciones" => $planificaciones,
        "total" => count($planificaciones)
    ]);
} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage()
    ], 500);
}