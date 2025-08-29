<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $res = pg_query($conn, "SELECT * FROM public.cuentas_contables order by nombre_contable ASC");

    if (!$res) {
        throw new Exception("Error en la consulta: " . pg_last_error($conn));
    }

    $data = pg_fetch_all($res) ?? [];

    echo json_encode([
        "success" => true,
        "message" => "cuentas obtenidos correctamente",
        "cuentas" => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage(),
        "cuentas" => []
    ]);
}
?>
