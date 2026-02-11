<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $sql = "SELECT id_categoria, nombre_categoria 
            FROM categorias 
            ORDER BY nombre_categoria ASC";

    $result = pg_query($conn, $sql);

    if (!$result) {
        json_response(["success" => false, "message" => "Error al obtener categorías: " . pg_last_error($conn)], 500);
    }

    $categorias = pg_fetch_all($result) ?: [];
    json_response(["success" => true, "categorias" => $categorias]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>