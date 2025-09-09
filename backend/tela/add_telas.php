<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!isset($data['tela']) || trim($data['tela']) === '') {
        json_response(["success" => false, "message" => "El campo 'tela' es obligatorio"], 400);
    }

    $tela = trim($data['tela']);

    // Verificar duplicado
    $check = pg_query_params($conn, "SELECT 1 FROM list_tela WHERE tela = $1 LIMIT 1", [$tela]);
    if (pg_num_rows($check) > 0) {
        json_response(["success" => false, "message" => "La tela ya existe"], 409);
    }

    $sql = "INSERT INTO list_tela (tela) VALUES ($1)
            RETURNING list_tela_id, tela, create_at";
    $params = [$tela];

    $result = pg_query_params($conn, $sql, $params);
    if (!$result) {
        json_response(["success" => false, "message" => "Error al crear tela: " . pg_last_error($conn)], 500);
    }

    $newTela = pg_fetch_assoc($result);
    json_response(["success" => true, "message" => "Tela creada exitosamente", "tela" => $newTela]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
