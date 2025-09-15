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

    $num_orden   = $input['num_orden'] ?? null;
    
    if(empty($num_orden)){
       json_response([
        'success' => false,
        'message'   => 'numero de orden requerido'
    ]);
    }


    $sql = "
    SELECT 
        -- DATOS PRINCIPALES DEL TRABAJO
        planificacion_work.department_id,
        departments.name_department,
        type_work.type_work, 
        type_work.image_path,
        m.hoja_produccion_id, 
        m.start_date, 
        m.end_date,
        m.created_at,
        m.estado_hoja_producion, 
        m.type_work_id,
        m.planificacion_work_id,
        m.comentario_producion,
        pre_orden.name_logo,

        -- Informacion Ficha
        list_ficha_available.ficha,
        m.ficha_id,
        m.num_orden,
        item_pre_orden.cant as cant_orden,

        -- Usuario del trabajo
        m.usuario_id,
        usuarios.nombre,
        usuarios.username,

        -- Parte de campos de trabajos 
        hoja_produccion_campos.hoja_produccion_campos_id,
        campos_type_work.nombre_campo,
        hoja_produccion_campos.campos_type_work_id,
        hoja_produccion_campos.cant

    FROM public.hoja_produccion as m
    LEFT JOIN public.hoja_produccion_campos 
        ON hoja_produccion_campos.hoja_produccion_id = m.hoja_produccion_id
    INNER JOIN public.usuarios 
        ON usuarios.id_usuario = m.usuario_id
    INNER JOIN public.list_ficha_available 
        ON list_ficha_available.ficha_id = m.ficha_id 
    INNER JOIN public.campos_type_work 
        ON campos_type_work.campos_type_work_id = hoja_produccion_campos.campos_type_work_id
    INNER JOIN public.type_work 
        ON type_work.type_work_id = m.type_work_id
    INNER JOIN public.planificacion_work 
        ON planificacion_work.planificacion_work_id = m.planificacion_work_id
    INNER JOIN public.item_pre_orden 
        ON item_pre_orden.item_pre_orden_id = planificacion_work.item_pre_orden_id
    INNER JOIN public.departments 
        ON departments.department_id = planificacion_work.department_id
    INNER JOIN public.pre_orden ON pre_orden.pre_orden_id = item_pre_orden.pre_orden_id
    WHERE m.num_orden = $1 
    ORDER BY m.hoja_produccion_id ASC";
    
    $res = pg_query_params($conn, $sql, [$num_orden]);

    if (!$res) {
        throw new Exception('Error en la consulta: ' . pg_last_error($conn));
    }

    $data = pg_fetch_all($res) ?? [];

    $grouped = [];

    foreach ($data as $row) {
        $key = $row['hoja_produccion_id'];

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                // Datos principales del trabajo
                'hoja_produccion_id'   => $row['hoja_produccion_id'],
                'start_date'           => $row['start_date'],
                'end_date'             => $row['end_date'],
                'created_at'           => $row['created_at'],
                'estado_hoja_producion'=> $row['estado_hoja_producion'],
                'type_work_id'         => $row['type_work_id'],
                'planificacion_work_id'=> $row['planificacion_work_id'],
                'comentario_producion' => $row['comentario_producion'],
                'name_logo' => $row['name_logo'],
                // Datos del tipo de trabajo
                'type_work'            => $row['type_work'],
                'image_path'           => $row['image_path'],

                // Datos del departamento
                'department_id'        => $row['department_id'],
                'name_department'      => $row['name_department'],

                // Información de ficha
                'ficha_id'             => $row['ficha_id'],
                'ficha'                => $row['ficha'],
                'num_orden'            => $row['num_orden'],
                'cant_orden'           => $row['cant_orden'],

                // Usuario
                'usuario_id'           => $row['usuario_id'],
                'nombre'               => $row['nombre'],
                'username'             => $row['username'],

                // Campos
                'campos' => []
            ];
        }

        // Agregar campos si existen
        if (!empty($row['hoja_produccion_campos_id'])) {
            $grouped[$key]['campos'][] = [
                'hoja_produccion_campos_id' => $row['hoja_produccion_campos_id'],
                'campos_type_work_id'       => $row['campos_type_work_id'],
                'nombre_campo'              => $row['nombre_campo'],
                'cant'                      => $row['cant']
            ];
        }
    }

    $result = array_values($grouped);

    json_response([
        'success' => true,
        'count'   => count($result),
        'data'    => $result
    ]);

} catch (Exception $e) {
    http_response_code(500);
    json_response([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
