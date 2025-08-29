<?php
include '../conexion.php';
include '../utils.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data['id_usuario']) {
  json_response(["error" => "ID de usuario requerido"], 400);
}

$sql = "UPDATE usuarios SET nombre=$1, username=$2, rol_id=$3, activo=$4 WHERE id_usuario=$5";

pg_query_params($conn, $sql, [
  $data['nombre'], $data['username'], $data['rol_id'], $data['activo'], $data['id_usuario']
]);

json_response(["ok" => true]);
