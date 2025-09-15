<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

$data = json_decode(file_get_contents("php://input"), true);
$hoja_id = isset($data['hoja_produccion_id']) ? intval($data['hoja_produccion_id']) : 0;

if ($hoja_id <= 0) {
    json_response([
        "success" => false,
        "message" => "ID inválido para hoja de producción"
    ], 400);
    exit;
}

// Verificar si se puede eliminar
$sql_check = "SELECT estado_hoja_producion FROM public.hoja_produccion WHERE hoja_produccion_id = $1";
$res_check = pg_query_params($conn, $sql_check, [$hoja_id]);

if (!$res_check || pg_num_rows($res_check) === 0) {
    json_response([
        "success" => false,
        "message" => "Hoja de producción no encontrada"
    ], 404);
    exit;
}

$estado = pg_fetch_result($res_check, 0, 'estado_hoja_producion');

if (!in_array($estado, ['FINALIZADO', 'CANCELADO'])) {
    json_response([
        "success" => false,
        "message" => "No se puede eliminar una hoja en estado '$estado'"
    ]);
    exit;
}

// Eliminar hoja
pg_query($conn, "BEGIN");

$sql_delete = "DELETE FROM public.hoja_produccion WHERE hoja_produccion_id = $1";
$res_delete = pg_query_params($conn, $sql_delete, [$hoja_id]);

if (!$res_delete) {
    pg_query($conn, "ROLLBACK");
    json_response([
        "success" => false,
        "message" => "Error al eliminar la hoja de producción"
    ]);
    exit;
}

pg_query($conn, "COMMIT");
json_response([
    "success" => true,
    "message" => "Hoja de producción eliminada correctamente"
]);