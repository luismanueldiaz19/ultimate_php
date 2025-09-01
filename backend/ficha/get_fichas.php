<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $res = pg_query($conn, "SELECT ficha_id, ficha, color_ficha, creado_en_ficha, actualizado_en_ficha
	FROM public.list_ficha_available order by ficha_id ASC");

    if (!$res) {
        throw new Exception("Error en la consulta: " . pg_last_error($conn));
    }

    $data = pg_fetch_all($res) ?? [];

    json_response([
        "success" => true,
        "message" => "Unidades de medida obtenidas correctamente",
        "fichas" => $data
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage(),
        "fichas" => []
    ]);
}
?>
