<?php
include '../conexion.php';
include '../utils.php';

try {
    // Consulta SQL directa sin parámetros
    $query = "SELECT department_id, name_department, statu, nivel, type, date, path_image
              FROM public.departments";

    // Ejecutar consulta
    $result = pg_query($conn, $query);

    if (!$result) {
        throw new Exception("Error en la consulta: " . pg_last_error($conn));
    }

    // Obtener resultados
    $depart = pg_fetch_all($result) ?? [];

    // Respuesta exitosa
    json_response([
        'success' => true,
        'department' => $depart,
    ]);
} catch (Exception $e) {
    // Respuesta con error
    json_response([
        'success' => false,
        'error' => $e->getMessage(),
    ], 400);
}