<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $input = json_decode(file_get_contents("php://input"), true);

    if (
        empty($input['nombre']) ||
        empty($input['username']) ||
        empty($input['password']) ||
        empty($input['registed_by'])
    ) {
        throw new Exception("Faltan campos obligatorios");
    }

    // Verificar si el username ya existe
    $checkQuery = "SELECT 1 FROM public.usuarios WHERE username = $1 LIMIT 1";
    $checkResult = pg_query_params($conn, $checkQuery, [$input['username']]);

    if (pg_num_rows($checkResult) > 0) {
        echo json_encode([
            "success" => false,
            "message" => "El nombre de usuario ya está registrado"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);

    $departAcceso = !empty($input['depart_acceso']) && is_array($input['depart_acceso'])
        ? '{' . implode(',', array_map('pg_escape_string', $input['depart_acceso'])) . '}'
        : '{}';

    $query = "INSERT INTO public.usuarios(
        nombre, username, password_hash, registed_by, depart_acceso
    ) VALUES (
        $1, $2, $3, $4, $5::text[]
    ) RETURNING id_usuario";

    $params = [
        $input['nombre'],
        $input['username'],
        $passwordHash,
        $input['registed_by'],
        $departAcceso
    ];

    $result = pg_query_params($conn, $query, $params);

    if (!$result) {
        throw new Exception("Error al insertar el usuario");
    }

    $inserted = pg_fetch_assoc($result);

    echo json_encode([
        "success" => true,
        "message" => "Usuario creado correctamente",
        "id_usuario" => $inserted['id_usuario']
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}