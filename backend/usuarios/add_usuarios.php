<?php
include '../conexion.php';
include '../utils.php';

try {
    // Decodificar JSON del body
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        json_response(["error" => "Entrada inválida, se esperaba JSON"], 400);
    }

    $username    = trim($data['username'] ?? '');
    $password    = $data['password'] ?? '';
    $nombre      = trim($data['nombre'] ?? '');
    $registed_by = $data['registed_by'] ?? '';
    $rol_id      = $data['rol_id'] ?? null;

    // Validar campos obligatorios individualmente
    $errores = [];
    if (empty($username))    $errores[] = "username";
    if (empty($password))    $errores[] = "password";
    if (empty($nombre))      $errores[] = "nombre";
    if (empty($registed_by)) $errores[] = "registed_by";

    if (!empty($errores)) {
        json_response([
            "error" => "Faltan campos obligatorios",
            "campos_faltantes" => $errores
        ], 400);
    }

    // Validación de longitud
    if (strlen($username) < 3) {
        json_response(["error" => "El nombre de usuario debe tener al menos 3 caracteres"], 422);
    }
    if (strlen($password) < 6) {
        json_response(["error" => "La contraseña debe tener al menos 6 caracteres"], 422);
    }

    // Verificar si ya existe el usuario
    $res = pg_query_params(
        $conn, 
        "SELECT 1 FROM usuarios WHERE username = $1 LIMIT 1", 
        [$username]
    );

    if (pg_num_rows($res) > 0) {
        json_response(["error" => "El usuario ya existe"], 409);
    }

    // Hash seguro de la contraseña
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]);

    // Insertar usuario
    $sql = "INSERT INTO usuarios (nombre, username, password_hash, rol_id, registed_by)
            VALUES ($1, $2, $3, $4, $5) RETURNING id_usuario";

    $result = pg_query_params($conn, $sql, [$nombre, $username, $passwordHash, $rol_id, $registed_by]);

    if (!$result) {
        json_response(["error" => "Error al insertar usuario"], 500);
    }

    $newUser = pg_fetch_assoc($result);

    json_response([
        "ok" => true,
        "id_usuario" => $newUser['id_usuario'],
        "username" => $username,
        "nombre" => $nombre,
        "rol_id" => $rol_id
    ]);

} catch (Exception $e) {
    json_response(["error" => "Excepción: " . $e->getMessage()], 500);
}
