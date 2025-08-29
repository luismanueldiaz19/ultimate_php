<?php
include '../conexion.php';
include '../utils.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data['id_usuario'] || !$data['nueva_password']) {
  json_response(["error" => "ID y nueva contraseña requeridos"], 400);
}

$hash = password_hash($data['nueva_password'], PASSWORD_BCRYPT);
pg_query_params($conn, "UPDATE usuarios SET password_hash=$1 WHERE id_usuario=$2", [$hash, $data['id_usuario']]);

json_response(["ok" => true]);
