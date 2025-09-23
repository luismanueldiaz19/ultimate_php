<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    // Leer el cuerpo JSON
    $input = json_decode(file_get_contents("php://input"), true);

    if (!isset($input['permisos']) || !is_array($input['permisos'])) {
        throw new Exception("Formato inválido: se esperaba una lista de permisos");
    }

    $insertados = [];
    $duplicados = [];

    foreach ($input['permisos'] as $permiso) {
        $id_usuario = $permiso['id_usuario'] ?? null;
        $modulo_action_id = $permiso['modulo_action_id'] ?? null;

        if (!$id_usuario || !$modulo_action_id) {
            continue; // Saltar si falta información
        }

        // Verificar si ya existe
        $check = pg_query_params($conn, "
            SELECT 1 FROM public.permission_modulo_users 
            WHERE id_usuario = $1 AND modulo_action_id = $2
        ", [$id_usuario, $modulo_action_id]);

        if (pg_num_rows($check) > 0) {
            $duplicados[] = $permiso;
            continue;
        }

        // Insertar si no existe
        $res = pg_query_params($conn, "
            INSERT INTO public.permission_modulo_users (id_usuario, modulo_action_id)
            VALUES ($1, $2)
        ", [$id_usuario, $modulo_action_id]);

        if ($res) {
            $insertados[] = $permiso;
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Permisos procesados",
        "insertados" => $insertados,
        "duplicados" => $duplicados
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "insertados" => [],
        "duplicados" => []
    ], JSON_UNESCAPED_UNICODE);
}