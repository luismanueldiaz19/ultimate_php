<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!isset($data['list_tela_id']) || !isset($data['tela'])) {
        json_response(["success" => false, "message" => "Faltan campos obligatorios"], 400);
    }

    $id   = intval($data['list_tela_id']);
    $tela = trim($data['tela']);

    // Verificar duplicado
    $check = pg_query_params($conn, "SELECT 1 FROM list_tela WHERE tela = $1 AND list_tela_id != $2 LIMIT 1", [$tela, $id]);
    if (pg_num_rows($check) > 0) {
        json_response(["success" => false, "message" => "La tela ya existe"], 409);
    }

    $sql = "UPDATE list_tela SET tela = $1 WHERE list_tela_id = $2
            RETURNING list_tela_id, tela, create_at";
    $params = [$tela, $id];

    $result = pg_query_params($conn, $sql, $params);
    if (!$result || pg_affected_rows($result) === 0) {
        json_response(["success" => false, "message" => "Tela no encontrada o no actualizada"], 404);
    }

    $updatedTela = pg_fetch_assoc($result);
    json_response(["success" => true, "message" => "Tela actualizada exitosamente", "tela" => $updatedTela]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
