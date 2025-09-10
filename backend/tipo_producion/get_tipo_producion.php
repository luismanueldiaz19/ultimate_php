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
                campos_type_work.nombre_campo
              FROM public.type_work AS m
              INNER JOIN public.departments ON departments.department_id = m.department_id
              LEFT JOIN public.campos_type_work ON campos_type_work.type_work_id = m.type_work_id
              ORDER BY departments.name_department ASC";

    $result = pg_query($conn, $query);

    if (!$result) {
        throw new Exception("Error en la consulta: " . pg_last_error($conn));
    }

    $rows = pg_fetch_all($result) ?? [];

    // Agrupar por departamento y luego por type_work
    $grouped = [];

    foreach ($rows as $row) {
        $deptName = $row['name_department'];
        $typeWorkId = $row['type_work_id'];

        // Inicializar departamento si no existe
        if (!isset($grouped[$deptName])) {
            $grouped[$deptName] = [];
        }

        // Buscar si ya existe el type_work dentro del departamento
        $found = false;
        foreach ($grouped[$deptName] as &$tw) {
            if ($tw['type_work_id'] === $typeWorkId) {
                // Agregar campo si existe
                if (!empty($row['campos_type_work_id'])) {
                    $tw['campos'][] = [
                        'campos_type_work_id' => $row['campos_type_work_id'],
                        'nombre_campo' => $row['nombre_campo']
                    ];
                }
                $found = true;
                break;
            }
        }

        // Si no existe, crear nuevo type_work
        if (!$found) {
            $grouped[$deptName][] = [
                'type_work_id' => $row['type_work_id'],
                'type_work' => $row['type_work'],
                'image_path' => $row['image_path'],
                'department_id' => $row['department_id'],
                'campos' => !empty($row['campos_type_work_id']) ? [[
                    'campos_type_work_id' => $row['campos_type_work_id'],
                    'nombre_campo' => $row['nombre_campo']
                ]] : []
            ];
        }
    }

    json_response([
        'success' => true,
        'data' => $grouped
    ]);

} catch (Exception $e) {
    json_response([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>