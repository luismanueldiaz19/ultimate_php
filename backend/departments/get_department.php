<?php
include '../conexion.php';
include '../utils.php';

try {
    // Leer y decodificar el JSON recibido
    $data = json_decode(file_get_contents("php://input"), true);

    // Validar que el campo 'depart' exista y no esté vacío
    if (!isset($data['depart']) || trim($data['depart']) === "") {
        throw new Exception("Parámetros vacíos: 'depart' es requerido.");
    }

    // Convertir la cadena en un array limpio
    $departamentosRaw = trim($data['depart']);
    
    $departamentos = array_filter(array_map('trim', explode(",", $departamentosRaw)));

    if (empty($departamentos)) {
        throw new Exception("La lista de departamentos está vacía.");
    }

    // Construir placeholders seguros ($1, $2, ...)
    $placeholders = [];
    for ($i = 1; $i <= count($departamentos); $i++) {
        $placeholders[] = '$' . $i;
    }
    $placeholdersStr = implode(",", $placeholders);

    // Consulta SQL con placeholders
    $query = "SELECT department_id, name_department, statu, nivel, type, date ,path_image
              FROM public.departments 
              WHERE type IN ($placeholdersStr)";    

    // Ejecutar consulta con parámetros seguros
    $result = pg_query_params($conn, $query, $departamentos);

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