<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $input = json_decode(file_get_contents("php://input"), true);

    $id_usuario = $input['id_usuario'] ?? null;
    $modulo_action_id = $input['modulo_action_id'] ?? null;

    if (!$id_usuario || !$modulo_action_id) {
        throw new Exception("Faltan datos: se requiere id_usuario y modulo_action_id");
    }

    // Verificar existencia
    $check = pg_query_params($conn, "
        SELECT 1 FROM public.permission_modulo_users 
        WHERE id_usuario = $1 AND modulo_action_id = $2
    ", [$id_usuario, $modulo_action_id]);

    if (pg_num_rows($check) === 0) {
        throw new Exception("El permiso no existe");
    }

    // Eliminar
    $res = pg_query_params($conn, "
        DELETE FROM public.permission_modulo_users 
        WHERE id_usuario = $1 AND modulo_action_id = $2
    ", [$id_usuario, $modulo_action_id]);

    if (!$res) {
        throw new Exception("Error al eliminar el permiso");
    }

    echo json_encode([
        "success" => true,
        "message" => "Permiso eliminado correctamente",
        "id_usuario" => $id_usuario,
        "modulo_action_id" => $modulo_action_id
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}