<?php
include '../conexion.php';
include '../utils.php';

try {
    $res = pg_query($conn, "
        SELECT 
            m.modulo_id, 
            m.modulo_name, 
            ma.modulo_action_id,  
            ma.name_action
        FROM public.modulo m
        JOIN public.modulo_action ma ON m.modulo_id = ma.modulo_id
        ORDER BY m.modulo_id, ma.name_action DESC
    ");

    if (!$res) {
        throw new Exception("Error en la consulta");
    }

    $rows = pg_fetch_all($res) ?? [];

    echo json_encode([
        "success" => true,
        "message" => "Acciones de módulos obtenidas correctamente",
        "data" => $rows
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "data" => []
    ], JSON_UNESCAPED_UNICODE);
}