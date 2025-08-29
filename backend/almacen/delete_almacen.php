<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // id_almacen obligatorio
    if (!isset($data['id_almacen']) || trim($data['id_almacen']) === '') {
        json_response(["success" => false, "message" => "Falta el id del almacén"], 400);
    }
    $id = (int)$data['id_almacen'];

    // Verificar si existe el almacén
    $check = pg_query_params($conn, "SELECT 1 FROM almacenes WHERE id_almacen = $1", [$id]);
    if (pg_num_rows($check) === 0) {
        json_response(["success" => false, "message" => "El almacén no existe"], 404);
    }

    // Eliminar el almacén
    $sql = "DELETE FROM almacenes WHERE id_almacen = $1";
    $result = @pg_query_params($conn, $sql, [$id]);

    if (!$result) {
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al eliminar almacén: " . $error], 500);
    }

    json_response([
        "success" => true,
        "message" => "Almacén eliminado exitosamente",
        "id_almacen" => $id
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
