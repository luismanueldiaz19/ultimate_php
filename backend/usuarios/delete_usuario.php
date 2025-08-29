<?php
include '../conexion.php';
include '../utils.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data['id_usuario']) {
  json_response(["error" => "ID requerido"], 400);
}

pg_query_params($conn, "UPDATE usuarios SET activo = FALSE WHERE id_usuario = $1", [$data['id_usuario']]);

json_response(["ok" => true]);
