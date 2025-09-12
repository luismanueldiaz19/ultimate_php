<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Validar que se recibió el ID
    if (!isset($data['type_work_id']) || trim($data['type_work_id']) === '') {
        json_response([
            "success" => false,
            "message" => "Falta el campo obligatorio: type_work_id"
        ], 400);
    }

    $type_work_id = trim($data['type_work_id']);

    // Verificar que exista
    $checkSql = "SELECT 1 FROM public.type_work WHERE type_work_id = $1";
    $checkResult = pg_query_params($conn, $checkSql, [$type_work_id]);

    if (!$checkResult || pg_num_rows($checkResult) === 0) {
        json_response([
            "success" => false,
            "message" => "No se encontró el tipo de trabajo con ese ID"
        ], 404);
    }

    // Eliminar directamente en type_work
    $deleteSql = "DELETE FROM public.type_work WHERE type_work_id = $1";
    $deleteResult = pg_query_params($conn, $deleteSql, [$type_work_id]);

    if (!$deleteResult) {
        throw new Exception("Error al eliminar tipo de trabajo: " . pg_last_error($conn));
    }

    json_response([
        "success" => true,
        "message" => "Tipo de trabajo eliminado exitosamente"
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage()
    ], 500);
}