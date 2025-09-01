<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!isset($data['productos_catalogos_id']) || !is_numeric($data['productos_catalogos_id'])) {
        json_response(["success" => false, "message" => "ID inválido"], 400);
    }

    $id = intval($data['productos_catalogos_id']);
    $sql = "DELETE FROM productos_catalogos WHERE productos_catalogos_id = $1 RETURNING productos_catalogos_id";
    $result = pg_query_params($conn, $sql, [$id]);

    if (!$result || pg_num_rows($result) === 0) {
        json_response(["success" => false, "message" => "Producto no encontrado"], 404);
    }

    json_response(["success" => true, "message" => "Producto eliminado correctamente"]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
