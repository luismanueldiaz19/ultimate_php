<?php

include '../conexion.php';
include '../utils.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Leer JSON del body
$data = json_decode(file_get_contents("php://input"), true);
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

// Validar campos requeridos
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Usuario y contraseña requeridos.']);
    exit;
}

// Buscar usuario activo con su rol
$queryUser = "
    SELECT u.id_usuario, u.nombre, u.username, u.password_hash, u.rol_id, u.activo, r.nombre AS rol_nombre,u.depart_acceso
    FROM public.usuarios u
    JOIN public.roles r ON u.rol_id = r.id_rol
    WHERE u.username = $1 AND u.activo = TRUE
";

$resultUser = pg_query_params($conn, $queryUser, [$username]);

if (!$resultUser || pg_num_rows($resultUser) === 0) {
    echo json_encode(['success' => false, 'message' => 'Credenciales inválidas.']);
    exit;
}

$user = pg_fetch_assoc($resultUser);

// Verificar contraseña
if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Credenciales inválidas.']);
    exit;
}

// Obtener permisos por rol
$queryPermisos = "
    SELECT id_permiso, modulo, puede_ver, puede_editar, puede_borrar
    FROM public.permisos
    WHERE rol_id = $1
";

$resultPermisos = pg_query_params($conn, $queryPermisos, [$user['rol_id']]);

$permisos = [];
if ($resultPermisos && pg_num_rows($resultPermisos) > 0) {
    while ($permiso = pg_fetch_assoc($resultPermisos)) {
        $permisos[] = $permiso;
    }
}
 

// Convertir el array PostgreSQL a array PHP
$departRaw = $user['depart_acceso']; // "{a,k,g,f}"
$departAccess = explode(',', trim($departRaw, '{}'));
// Enviar respuesta de éxito
echo json_encode([
    'success' => true,
    'message' => 'Login exitoso.',
    'usuario' => [
        'id_usuario' => $user['id_usuario'],
        'usuario_nombre' => $user['nombre'],
        'username' => $user['username'],
        'rol_id' => $user['rol_id'],
        'rol_nombre' => $user['rol_nombre'],
        'depart_acceso' => $departAccess,
        'permisos' => $permisos
    ]
]);

// Liberar recursos
pg_free_result($resultUser);
if ($resultPermisos) {
    pg_free_result($resultPermisos);
}
pg_close($conn);