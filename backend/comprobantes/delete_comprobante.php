<?php

// File: delete_secuencia.php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

$data = json_decode(file_get_contents("php://input"), true);

$usuarioId = $data['usuario_id'] ?? null;

if (!$usuarioId) json_response(["error" => "Usuario no autenticado"], 401);

if (!$data['tipo_ncf']) {
  json_response(["error" => "tipo_ncf requerido"], 400);
}

$oldQuery = pg_query_params($conn, "SELECT * FROM secuencias_ncf WHERE tipo_ncf = $1", [$data['tipo_ncf']]);
$oldData = pg_fetch_assoc($oldQuery);

$result = pg_query_params($conn, "DELETE FROM secuencias_ncf WHERE tipo_ncf = $1", [$data['tipo_ncf']]);

registrarAuditoria($conn, $usuarioId, 'DELETE', 'secuencias_ncf', $oldData, null);

json_response(["success" => true, "message" => "Secuencia eliminada correctamente"]);
