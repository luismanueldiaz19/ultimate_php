<?php
// File: update_secuencia.php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

$data = json_decode(file_get_contents("php://input"), true);

$usuarioId = $data['usuario_id'] ?? null;
if (!$usuarioId) json_response(["error" => "Usuario no autenticado"], 401);

if (!$data['tipo_ncf']) {
  json_response(["error" => "tipo_ncf requerido"], 400);
}

// Obtener valores anteriores
$oldQuery = pg_query_params($conn, "SELECT * FROM secuencias_ncf WHERE tipo_ncf = $1", [$data['tipo_ncf']]);

$oldData = pg_fetch_assoc($oldQuery);

$sql = "UPDATE secuencias_ncf SET secuencia_actual = $1, prefijo = $2 , ncf_name  = $3 WHERE tipo_ncf = $4";
$params = [
  $data['secuencia_actual'] ?? $oldData['secuencia_actual'],
  $data['prefijo'] ?? $oldData['prefijo'],
  $data['ncf_name'],
  $data['tipo_ncf'],
];

$result = pg_query_params($conn, $sql, $params);

registrarAuditoria($conn, $usuarioId, 'UPDATE', 'secuencias_ncf', $oldData, $data);

json_response(["success" => true, "message" => "Secuencia actualizada correctamente"]);