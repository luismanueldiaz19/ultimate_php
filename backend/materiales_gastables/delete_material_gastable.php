<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!isset($data['materiales_gastable_id']) || !is_numeric($data['materiales_gastable_id'])) {
        json_response(["success" => false, "message" => "ID de material requerido o inválido"]);
    }

    $id = (int) $data['materiales_gastable_id'];

    // Validar existencia
    $check = pg_query_params($conn, "SELECT 1 FROM materiales_gastable WHERE materiales_gastable_id = $1", [$id]);
    if (pg_num_rows($check) === 0) {
        json_response(["success" => false, "message" => "El material no existe"]);
    }

    // Eliminar
    $sql = "DELETE FROM materiales_gastable WHERE materiales_gastable_id = $1";
    $result = pg_query_params($conn, $sql, [$id]);

    if (!$result) {
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al eliminar material: " . $error]);
    }

    json_response([
        "success" => true,
        "message" => "Material eliminado exitosamente"
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()]);
}
?>