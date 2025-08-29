<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!isset($data['id_producto'])) {
        json_response(["success" => false, "message" => "El id_producto es obligatorio"], 400);
    }

    $id_producto = (int)$data['id_producto'];

    // Opcional: verificar si existe antes de borrar
    $check = pg_query_params($conn, "SELECT 1 FROM productos WHERE id_producto = $1", [$id_producto]);
    if (pg_num_rows($check) === 0) {
        json_response(["success" => false, "message" => "El producto no existe"], 404);
    }

    $result = @pg_query_params($conn, "DELETE FROM productos WHERE id_producto = $1", [$id_producto]);
    if (!$result) {
        json_response(["success" => false, "message" => "Error al eliminar producto: " . pg_last_error($conn)], 500);
    }

    json_response(["success" => true, "message" => "Producto eliminado exitosamente"]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
