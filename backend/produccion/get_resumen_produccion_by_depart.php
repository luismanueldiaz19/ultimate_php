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

    // Leer entrada JSON
    $input = json_decode(file_get_contents('php://input'), true);

    $fields = [
        'fecha_inicio'        => $input['fecha_inicio'] ?? '',
        'fecha_fin'           => $input['fecha_fin'] ?? '',
        'nombre_departamento' => $input['nombre_departamento'] ?? '',
    ];

    // Escapar cada campo
    foreach ($fields as $key => $value) {
        $fields[$key] = pg_escape_string($conn, $value);
    }

    // Validar campos obligatorios
    $required = ['fecha_inicio', 'fecha_fin', 'nombre_departamento'];
    $empty_fields = [];
    foreach ($required as $field) {
        if (empty($fields[$field])) {
            $empty_fields[] = $field;
        }
    }

    if (!empty($empty_fields)) {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios incompletos',
            'empty_fields' => $empty_fields
        ]);
        exit;
    }

    // Consulta SQL
    $sql = "
        SELECT 
            d.department_id,
            d.name_department,
            usuarios.nombre AS usuario,
            tw.type_work,
            ctw.nombre_campo,
            SUM(hpc.cant) AS total_cant
        FROM public.hoja_produccion AS m
        LEFT JOIN public.hoja_produccion_campos AS hpc 
            ON hpc.hoja_produccion_id = m.hoja_produccion_id
        INNER JOIN public.campos_type_work AS ctw 
            ON ctw.campos_type_work_id = hpc.campos_type_work_id
        INNER JOIN public.type_work AS tw 
            ON tw.type_work_id = m.type_work_id
        INNER JOIN public.planificacion_work AS pw 
            ON pw.planificacion_work_id = m.planificacion_work_id
        INNER JOIN public.departments AS d 
            ON d.department_id = pw.department_id
        INNER JOIN public.usuarios 
            ON usuarios.id_usuario = m.usuario_id	
        WHERE DATE(m.end_date) BETWEEN $1 AND $2
          AND d.name_department = $3
          AND m.start_date IS NOT NULL 
          AND m.end_date IS NOT NULL
        GROUP BY 
            d.department_id, 
            d.name_department, 
            usuarios.nombre, 
            tw.type_work, 
            ctw.nombre_campo
        ORDER BY 
            d.name_department ASC, 
            tw.type_work ASC, 
            usuarios.nombre ASC;
    ";

    $params = [
        $fields['fecha_inicio'],
        $fields['fecha_fin'],
        $fields['nombre_departamento']
    ];

    $res = pg_query_params($conn, $sql, $params);

    if (!$res) {
        throw new Exception('Error en la consulta: ' . pg_last_error($conn));
    }

    $rows = pg_fetch_all($res) ?? [];

    // ✅ Agrupación por Departamento → Tipo de trabajo → Usuario → Campos
    $departamentos = [];

    foreach ($rows as $row) {
        $deptId = $row['department_id'];
        $deptName = $row['name_department'];
        $typeWork = $row['type_work'];
        $usuario = $row['usuario'];
        $nombreCampo = $row['nombre_campo'];
        $totalCant = (float)$row['total_cant'];

        // Crear departamento si no existe
        if (!isset($departamentos[$deptId])) {
            $departamentos[$deptId] = [
                'department_id'   => $deptId,
                'name_department' => $deptName,
                'trabajos'        => []
            ];
        }

        // Crear tipo de trabajo si no existe
        if (!isset($departamentos[$deptId]['trabajos'][$typeWork])) {
            $departamentos[$deptId]['trabajos'][$typeWork] = [
                'type_work' => $typeWork,
                'usuarios'  => []
            ];
        }

        // Crear usuario si no existe
        if (!isset($departamentos[$deptId]['trabajos'][$typeWork]['usuarios'][$usuario])) {
            $departamentos[$deptId]['trabajos'][$typeWork]['usuarios'][$usuario] = [
                'nombre' => $usuario,
                'campos' => []
            ];
        }

        // Agregar campo con su cantidad
        $departamentos[$deptId]['trabajos'][$typeWork]['usuarios'][$usuario]['campos'][] = [
            'nombre_campo' => $nombreCampo,
            'total_cant'   => $totalCant
        ];
    }

    // ✅ Convertir subarrays asociativos a arrays indexados
    $result = array_values(array_map(function ($dept) {
        $dept['trabajos'] = array_values(array_map(function ($trabajo) {
            $trabajo['usuarios'] = array_values($trabajo['usuarios']);
            return $trabajo;
        }, $dept['trabajos']));
        return $dept;
    }, $departamentos));

    // ✅ Respuesta final
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
