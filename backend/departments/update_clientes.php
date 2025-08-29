<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data['id_cliente']) {
  json_response(["error" => "ID requerido"], 400);
}

$usuarioId = $data['usuario_id'] ?? null;

if (!$usuarioId) {
  json_response(["error" => "Usuario no autenticado"], 401);
}

// Obtener datos anteriores
$res = pg_query_params($conn, "SELECT * FROM clientes WHERE id_cliente = $1", [$data['id_cliente']]);

$datos_anteriores = pg_fetch_assoc($res);

// Actualizar
$sql = "UPDATE clientes SET nombre=$1, rnc_cedula=$2, tipo_entidad=$3,
        tipo_identificacion=$4, email=$5, telefono=$6, direccion=$7
        WHERE id_cliente = $8";

$params = [
  $data['nombre'],
  $data['rnc_cedula'],
  $data['tipo_entidad'],
  $data['tipo_identificacion'],
  $data['email'],
  $data['telefono'],
  $data['direccion'],
  $data['id_cliente'],
];

pg_query_params($conn, $sql, $params);

// Registrar auditoría
registrarAuditoria($conn, $usuarioId, 'UPDATE', 'clientes', $datos_anteriores, $data);

json_response(["ok" => true]);
