<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

$nombre = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';

try {
    $sql = "
        SELECT codigo, nombre, nivel, tipo, padre
        FROM public.catalogo_cuentas
        WHERE 1=1
    ";

    if (!empty($nombre)) {
        $nombre = pg_escape_string($conn, $nombre);
        $sql .= " AND nombre ILIKE '%$nombre%'";
    }

    $sql .= " ORDER BY codigo ASC";

    $res = pg_query($conn, $sql);

    if (!$res) {
        throw new Exception(pg_last_error($conn));
    }

    $data = pg_fetch_all($res) ?? [];

    echo json_encode([
        "success" => true,
        "message" => "Cuentas filtradas correctamente",
        "cuentas" => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "cuentas" => []
    ]);
}
?>
