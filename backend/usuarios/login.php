<?php

include '../conexion.php';
include '../utils.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Leer JSON del body
$data = json_decode(file_get_contents("php://input"), true);
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (!$username || !$password) {
    // http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Usuario y contraseña requeridos.']);
    exit;
}

// 1. Buscar usuario y rol
$queryUser = "
    SELECT u.id_usuario, u.nombre, u.username, u.password_hash, u.rol_id, u.activo, r.nombre AS rol_nombre
    FROM public.usuarios u
    JOIN public.roles r ON u.rol_id = r.id_rol
    WHERE u.username = $1 AND u.activo = TRUE
";

$resultUser = pg_query_params($conn, $queryUser, [$username]);

if (!$resultUser || pg_num_rows($resultUser) === 0) {
    // http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Credenciales inválidas.']);
    exit;
}

$user = pg_fetch_assoc($resultUser);

// 2. Verificar contraseña
if (!password_verify($password, $user['password_hash'])) {
    // http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Credenciales inválidas.']);
    exit;
}

// 3. Obtener permisos por rol
$queryPermisos = "
    SELECT id_permiso, modulo, puede_ver, puede_editar, puede_borrar
    FROM public.permisos
    WHERE rol_id = $1
";
$resultPermisos = pg_query_params($conn, $queryPermisos, [$user['rol_id']]);

$permisos = [];
while ($permiso = pg_fetch_assoc($resultPermisos)) {
    $permisos[] = $permiso;
}

// 4. Enviar respuesta
echo json_encode([
    'success' => true,
    'message' => 'Login exitoso.',
    'usuario' => [
        'id_usuario' => $user['id_usuario'],
        'nombre' => $user['nombre'],
        'username' => $user['username'],
        'rol_id' => $user['rol_id'],
        'rol_nombre' => $user['rol_nombre'],
        'permisos' => $permisos
    ]
]);

pg_free_result($resultUser);
pg_free_result($resultPermisos);
pg_close($conn);
