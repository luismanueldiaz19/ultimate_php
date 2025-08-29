<?php
include '../conexion.php';
include '../utils.php';

$data = json_decode(file_get_contents("php://input"), true);

$sql = "INSERT INTO permisos (modulo, puede_ver, puede_editar, puede_borrar, rol_id)
        VALUES ($1, $2, $3, $4, $5)";

$params = [
  $data['modulo'],
  $data['puede_ver'] ?? false,
  $data['puede_editar'] ?? false,
  $data['puede_borrar'] ?? false,
  $data['rol_id']
];

pg_query_params($conn, $sql, $params);

json_response(["ok" => true]);
