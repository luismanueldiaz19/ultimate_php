<?php
include '../conexion.php';
include '../utils.php';


try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Método no permitido. Solo se acepta POST.');
    }

    if (!$conn) {
        throw new Exception('No se pudo establecer la conexión a la base de datos.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id_depart = $input['id_depart'] ?? null;

    if (!$id_depart) {
        throw new Exception('El campo "id_depart" es requerido.');
    }

    $sql = "
        SELECT 
            h.hoja_produccion_id,
            h.start_date,
            h.end_date,
            h.created_at,
            h.estado_hoja,
            users.full_name,
            h.usuario_id,
            h.orden_items_id,
            h.tipo_trabajo_id,
            h.observaciones_hoja,
            c.hoja_produccion_campos_id,
            c.campo_id,
            tipo_trabajo.name_trabajo,
            tipo_trabajo.url_imagen,
            tipo_trabajo.area_trabajo,
            trabajo_campos.nombre_campo,
            i.cant as cant_orden,
            c.cant,
            trabajo_campos.tipo_dato,
            l.num_orden,
            l.ficha,
            l.name_logo,
            l.id_cliente,
            cliente.nombre AS nombre_cliente,
            d.id AS id_depart,
            d.name_department
        FROM public.hoja_produccion h
        JOIN public.hoja_produccion_campos c ON h.hoja_produccion_id = c.hoja_produccion_id
        JOIN public.orden_items i ON i.orden_items_id = h.orden_items_id
        JOIN public.list_ordenes l ON l.list_ordenes_id = i.list_ordenes_id
        JOIN public.cliente ON cliente.id_cliente = l.id_cliente
        JOIN public.trabajo_campos ON trabajo_campos.campo_id = c.campo_id
        JOIN public.tipo_trabajo ON tipo_trabajo.tipo_trabajo_id = h.tipo_trabajo_id
        JOIN public.planificacion_work pw ON pw.orden_items_id = i.orden_items_id
        JOIN public.departments d ON d.id = pw.id_depart
        JOIN public.users ON users.id = h.usuario_id
       WHERE d.id = $1 AND h.estado_hoja != 'COMPLETADO'
        ORDER BY h.hoja_produccion_id DESC, c.hoja_produccion_campos_id
    ";

    $res = pg_query_params($conn, $sql, [$id_depart]);

    if (!$res) {
        throw new Exception('Error en la consulta: ' . pg_last_error($conn));
    }

    $data = pg_fetch_all($res) ?? [];

   $grouped = [];

foreach ($planificaciones as $row) {
    $key = $row['planificacion_work_id'];

    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'planificacion_work_id' => $row['planificacion_work_id'],
            'department_id' => $row['department_id'],
            'name_department' => $row['name_department'],
            'type' => $row['type'],
            'codigo_producto' => $row['codigo_producto'],
            'nombre_producto' => $row['nombre_producto'],
            'id_producto' => $row['id_producto'],
            'item_pre_orden_id' => $row['item_pre_orden_id'],
            'estado_planificacion_work' => $row['estado_planificacion_work'],
            'work_creado_en' => $row['work_creado_en'],
            'comentario_work' => $row['comentario_work'],
            'ficha' => $row['ficha'],
            'color_ficha' => $row['color_ficha'],
            'nota_producto' => $row['nota_producto'],
            'cant' => $row['cant'],
            'tela' => $row['tela'],
            'name_logo' => $row['name_logo'],
            'num_orden' => $row['num_orden'],
            'fecha_entrega' => $row['fecha_entrega'],
            'id_usuario' => $row['id_usuario'],
            'estado_hoja' => $row['estado_hoja'],
            'nombre' => $row['nombre'],
            'rnc_cedula' => $row['rnc_cedula'],
            'tipo_entidad' => $row['tipo_entidad'],
            'tipo_identificacion' => $row['tipo_identificacion'],
            'email' => $row['email'],
            'telefono' => $row['telefono'],
            'direccion' => $row['direccion'],
            'estado_item' => $row['estado_item'],
            'designs' => []
        ];
    }

    // Agrupar por design_tipo_id dentro del planificacion_work_id
    $designKey = $row['design_tipo_id'];
    $designsRef = &$grouped[$key]['designs'];

    if (!isset($designsRef[$designKey])) {
        $designsRef[$designKey] = [
            'design_tipo_id' => $row['design_tipo_id'],
            'tipo_trabajo' => $row['tipo_trabajo'],
            'imagenes' => []
        ];
    }

    // Agregar imagen si existe
    if (!empty($row['design_images_items_id'])) {
        $designsRef[$designKey]['imagenes'][] = [
            'design_images_items_id' => $row['design_images_items_id'],
            'comment_imagen' => $row['comment_imagen'],
            'body_ubicacion' => $row['body_ubicacion'],
            'ruta' => $row['ruta']
        ];
    }
}

// Convertir a array indexado
$result = array_values($grouped);
foreach ($result as &$item) {
    $item['designs'] = array_values($item['designs']);
}

    json_response([
        'success' => true,
         "planificaciones" => $result,
        'count' => count($result),
        'data' => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    json_response([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
