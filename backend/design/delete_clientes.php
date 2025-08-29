<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Método no permitido'], 405);
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['id_cliente'])) {
    json_response(['success' => false, 'message' => 'ID del cliente requerido'], 400);
}

if (empty($data['usuario_id'])) {
    json_response(['success' => false, 'message' => 'Usuario no autenticado'], 401);
}

$id_cliente = $data['id_cliente'];
$usuarioId = $data['usuario_id'];

// Obtener datos para auditoría
$res = pg_query_params($conn, "SELECT * FROM clientes WHERE id_cliente = $1", [$id_cliente]);

$datos_anteriores = pg_fetch_assoc($res);

if (!$datos_anteriores) {
    json_response(['success' => false, 'message' => 'Cliente no encontrado'], 404);
}

// Eliminar cliente
$result = pg_query_params($conn, "DELETE FROM clientes WHERE id_cliente = $1", [$id_cliente]);

if ($result) {
    // Registrar auditoría
    registrarAuditoria($conn, $usuarioId, 'DELETE', 'clientes', $datos_anteriores, null);
    
    json_response(['success' => true, 'message' => 'Cliente eliminado correctamente']);
} else {
    json_response(['success' => false, 'message' => 'Error al eliminar cliente: ' . pg_last_error($conn)], 500);
}

pg_close($conn);
