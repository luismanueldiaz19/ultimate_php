<?php
// File: create_secuencia.php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

$data = json_decode(file_get_contents("php://input"), true);

$usuarioId = $data['usuario_id'] ?? null;
if (!$usuarioId) json_response(["error" => "Usuario no autenticado"], 401);

if (!$data['tipo_ncf'] || !$data['prefijo']) {
  json_response(["error" => "tipo_ncf y prefijo son requeridos"], 400);
}

$sql = "INSERT INTO secuencias_ncf (tipo_ncf, secuencia_actual, prefijo,ncf_name) VALUES ($1, $2, $3,$4)";
$params = [
  $data['tipo_ncf'],
  $data['secuencia_actual'] ?? 1,
  $data['prefijo'],
  $data['ncf_name'],
];

$result = pg_query_params($conn, $sql, $params);

registrarAuditoria($conn, $usuarioId, 'INSERT', 'secuencias_ncf', null, $data);

json_response(["success" => true, "message" => "Secuencia creada correctamente"]);
