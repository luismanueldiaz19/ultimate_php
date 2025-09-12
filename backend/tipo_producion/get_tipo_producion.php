<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $query = "SELECT 
                m.type_work_id,
                departments.name_department,
                m.type_work,
                m.image_path,
                m.department_id,
                campos_type_work.campos_type_work_id,
                campos_type_work.type_work_id AS campo_type_work_id,
                campos_type_work.nombre_campo,
                campos_type_work.created_at
              FROM public.type_work AS m
              LEFT JOIN public.campos_type_work 
                ON campos_type_work.type_work_id = m.type_work_id
              INNER JOIN public.departments 
                ON departments.department_id = m.department_id
              ORDER BY departments.name_department,m.type_work ,campos_type_work.nombre_campo ASC";

    $result = pg_query($conn, $query);

    if (!$result) {
        json_response([
            'success' => false,
            'message' => "Error en la consulta: " . pg_last_error($conn)
        ]);
        exit;
    }

    $rows = pg_fetch_all($result);
    if ($rows === false) {
        json_response([
            'success' => false,
            'message' => "No se pudo obtener resultados"
        ]);
        exit;
    }

    $grouped = [];

    foreach ($rows as $row) {
        $deptId = $row['department_id'];
        $deptName = $row['name_department'];
        $typeWorkId = $row['type_work_id'];
        $typeWorkName = $row['type_work'];

        // Inicializar departamento
        if (!isset($grouped[$deptId])) {
            $grouped[$deptId] = [
                'department_id' => $deptId,
                'name_department' => $deptName,
                'type_works' => []
            ];
        }

        // Inicializar tipo de trabajo
        if (!isset($grouped[$deptId]['type_works'][$typeWorkId])) {
            $grouped[$deptId]['type_works'][$typeWorkId] = [
                'type_work_id' => $typeWorkId,
                'type_work' => $typeWorkName,
                'image_path' => $row['image_path'],
                'campos' => []
            ];
        }

        // Agregar campo si existe
        if (!empty($row['campos_type_work_id'])) {
            $grouped[$deptId]['type_works'][$typeWorkId]['campos'][] = [
                'campos_type_work_id' => $row['campos_type_work_id'],
                'nombre_campo' => $row['nombre_campo'],
                'created_at' => $row['created_at']
            ];
        }
    }

    // Convertir a array indexado
    $result = array_values(array_map(function ($dept) {
        $dept['type_works'] = array_values($dept['type_works']);
        return $dept;
    }, $grouped));

    json_response([
        'success' => true,
        'data' => $result
    ]);

} catch (Exception $e) {
    json_response([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>