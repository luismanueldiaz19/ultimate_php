<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

// Leer JSON del body
$data = json_decode(file_get_contents("php://input"), true);

// Validación básica
if (empty($data['nombre'])) {
    json_response(["success" => false, "message" => "El nombre del cliente es obligatorio."], 400);
}

$usuarioId = $data['usuario_id'] ?? null;
if (!$usuarioId) {
    json_response(["success" => false, "message" => "Usuario no autenticado."], 401);
}

$sql = "INSERT INTO clientes 
    (nombre, rnc_cedula, tipo_entidad, tipo_identificacion, email, telefono, direccion)
    VALUES ($1, $2, $3, $4, $5, $6, $7)";

$params = [
    $data['nombre'],
    $data['rnc_cedula'] ?? null,
    $data['tipo_entidad'] ?? 'FISICA',
    $data['tipo_identificacion'] ?? 'CEDULA',
    $data['email'] ?? null,
    $data['telefono'] ?? null,
    $data['direccion'] ?? null,
];

$result = pg_query_params($conn, $sql, $params);

// Verificar éxito de la inserción
if (!$result) {
    json_response(["success" => false, "message" => "Error al guardar cliente."], 500);
}

// Auditoría
registrarAuditoria(
    $conn,
    $usuarioId,
    'INSERT',
    'clientes',
    null,
    $data
);

// OK
json_response([
    "success" => true,
    "message" => "Cliente creado exitosamente"
]);
