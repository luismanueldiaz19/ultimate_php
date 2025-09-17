<?php 
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Validar campo obligatorio
    if (!isset($data['tipo_movimiento']) || trim($data['tipo_movimiento']) === '') {
        json_response([
            "success" => false,
            "message" => "El campo 'tipo_movimiento' es obligatorio"
        ], 400);
    }

    $tipo = trim($data['tipo_movimiento']);

    // Verificar duplicado
    $check = pg_query_params($conn, "SELECT 1 FROM tipo_movimiento WHERE tipo_movimiento = $1 LIMIT 1", [$tipo]);
    if (pg_num_rows($check) > 0) {
        json_response(["success" => false, "message" => "El tipo de movimiento ya existe"], 409);
    }

    // Insertar
    $sql = "INSERT INTO tipo_movimiento (tipo_movimiento)
            VALUES ($1)
            RETURNING tipo_movimiento_id, tipo_movimiento";
    $result = pg_query_params($conn, $sql, [$tipo]);

    if (!$result) {
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al crear tipo de movimiento: " . $error], 500);
    }

    $nuevoTipo = pg_fetch_assoc($result);
    json_response([
        "success" => true,
        "message" => "Tipo de movimiento creado exitosamente",
        "tipo_movimiento" => $nuevoTipo
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}