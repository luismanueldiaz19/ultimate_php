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

// Consulta con LEFT JOIN para obtener acciones
$queryUser = "SELECT m.id_usuario, m.password_hash, m.secret_otp, m.nombre, m.username, m.activo,
m.creado_en, m.registed_by, m.depart_acceso, m.is_master,ma.modulo_id,
pmu.modulo_action_id, mn.modulo_name, ma.name_action
FROM public.usuarios AS m
LEFT JOIN public.permission_modulo_users pmu ON pmu.id_usuario = m.id_usuario 
LEFT JOIN public.modulo_action ma ON ma.modulo_action_id = pmu.modulo_action_id
LEFT JOIN public.modulo mn ON mn.modulo_id = ma.modulo_id
WHERE m.username = $1 AND m.activo = TRUE";

$resultUser = pg_query_params($conn, $queryUser, [$username]);

if (!$resultUser || pg_num_rows($resultUser) === 0) {
    echo json_encode(['success' => false, 'message' => 'Credenciales inválidas.']);
    exit;
}

$rows = pg_fetch_all($resultUser);
$userBase = $rows[0]; // Para verificar contraseña

// Verificar contraseña
if (!password_verify($password, $userBase['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Credenciales inválidas.']);
    exit;
}

// Agrupar acciones por usuario
$usuarios = [];

foreach ($rows as $row) {
    $idUsuario = $row['id_usuario'];

    if (!isset($usuarios[$idUsuario])) {
        $departAcceso = is_string($row['depart_acceso'])
            ? array_filter(explode(',', trim($row['depart_acceso'], '{}')))
            : [];

        $usuarios[$idUsuario] = [
            "id_usuario" => $row['id_usuario'],
            "usuario_nombre" => $row['nombre'],
            "username" => $row['username'],
            "activo" => $row['activo'],
            "creado_en" => $row['creado_en'],
            "registed_by" => $row['registed_by'],
            "depart_acceso" => $departAcceso,
            "is_master" => $row['is_master'] === 't',
            "modulos" => []
        ];
    }

    if (!empty($row['modulo_action_id'])) {
        $usuarios[$idUsuario]['modulos'][] = [
            "modulo_id" => $row['modulo_id'],
            "modulo_action_id" => $row['modulo_action_id'],
            "modulo_name" => $row['modulo_name'],
            "name_action" => $row['name_action']
        ];
    }
}

// Enviar respuesta
echo json_encode([
    'success' => true,
    'message' => 'Login exitoso.',
    'usuario' => reset($usuarios)
]);

// Liberar recursos
pg_free_result($resultUser);
pg_close($conn);