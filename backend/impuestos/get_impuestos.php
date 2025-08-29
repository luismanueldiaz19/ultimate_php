<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $res = pg_query($conn, "SELECT id_impuesto, nombre_impuesto, porcentaje_impuesto, statu_impuesto, creado_en, actualizado_en 
                            FROM impuestos
                            ORDER BY id_impuesto ASC");

    if (!$res) {
        throw new Exception("Error en la consulta: " . pg_last_error($conn));
    }

    $data = pg_fetch_all($res) ?? [];

    echo json_encode([
        "success" => true,
        "message" => "Impuestos obtenidos correctamente",
        "impuestos" => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage(),
        "impuestos" => []
    ]);
}
?>
