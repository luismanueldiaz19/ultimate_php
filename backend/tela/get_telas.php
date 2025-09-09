<?php
include '../conexion.php';
include '../utils.php';
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $sql = "SELECT list_tela_id, tela, create_at 
            FROM list_tela 
            ORDER BY tela ASC";

    $result = pg_query($conn, $sql);

    if (!$result) {
        json_response([
            "success" => false, 
            "message" => "Error al consultar telas: " . pg_last_error($conn)
        ], 500);
    }

    $telas = pg_fetch_all($result) ?: [];
    json_response([
        "success" => true,
        "telas" => $telas
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false, 
        "message" => "Excepción: " . $e->getMessage()
    ], 500);
}
?>
