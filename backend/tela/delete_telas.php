<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!isset($data['list_tela_id'])) {
        json_response(["success" => false, "message" => "El campo 'list_tela_id' es obligatorio"], 400);
    }

    $id = intval($data['list_tela_id']);

    $sql = "DELETE FROM list_tela WHERE list_tela_id = $1";
    $result = pg_query_params($conn, $sql, [$id]);

    if (!$result || pg_affected_rows($result) === 0) {
        json_response(["success" => false, "message" => "Tela no encontrada o no eliminada"], 404);
    }

    json_response(["success" => true, "message" => "Tela eliminada exitosamente"]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
