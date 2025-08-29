<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $res = pg_query($conn, "
        SELECT 
            id_unidad, 
            nombre_medida, 
            abreviatura_medida, 
            statu_medida, 
            creado_en_medida, 
            actualizado_en_medida
        FROM unidades_medida
        ORDER BY id_unidad
    ");

    if (!$res) {
        throw new Exception("Error en la consulta: " . pg_last_error($conn));
    }

    $data = pg_fetch_all($res) ?? [];

    json_response([
        "success" => true,
        "message" => "Unidades de medida obtenidas correctamente",
        "unidades" => $data
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage(),
        "unidades" => []
    ]);
}
?>
