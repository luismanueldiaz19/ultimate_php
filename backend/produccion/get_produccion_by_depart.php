<?php
include '../conexion.php';
include '../utils.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

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

    // Agrupar por name_trabajo
    $grouped = [];

    foreach ($data as $row) {
        $key = $row['hoja_produccion_id'];
        
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'tipo_trabajo_id' => $row['tipo_trabajo_id'],
                'hoja_produccion_id' => $row['hoja_produccion_id'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'created_at' => $row['created_at'],
                'full_name' => $row['full_name'],
                'estado_hoja' => $row['estado_hoja'],
                'usuario_id' => $row['usuario_id'],
                'observaciones_hoja' => $row['observaciones_hoja'],
                'hoja_produccion_campos_id' => $row['hoja_produccion_campos_id'],
                'name_trabajo' => $row['name_trabajo'],
                'url_imagen' => $row['url_imagen'],
                'area_trabajo' => $row['area_trabajo'],
                'id_depart' => $row['id_depart'],
                'name_department' => $row['name_department'],
                'orden_items_id' => $row['orden_items_id'],
                'num_orden' => $row['num_orden'],
                'ficha' => $row['ficha'],
                'name_logo' => $row['name_logo'],
                'id_cliente' => $row['id_cliente'],
                'cant_orden' => $row['cant_orden'],
                'nombre_cliente' => $row['nombre_cliente'],
                'campos' => []
            ];
        }

        if (!empty($row['campo_id'])) {
            $grouped[$key]['campos'][] = [
                'hoja_produccion_campos_id' => $row['hoja_produccion_campos_id'],
                'campo_id' => $row['campo_id'],
                'nombre_campo' => $row['nombre_campo'],
                'tipo_dato' => $row['tipo_dato'],
                'cant' => $row['cant'],
                'cant_orden' => $row['cant_orden']
            ];
        }
    }

    // Convertir a array indexado
    $result = array_values($grouped);

    json_response([
        'success' => true,
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
