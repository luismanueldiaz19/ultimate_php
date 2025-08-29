<?php
include '../conexion.php';
include '../utils.php';

$data = json_decode(file_get_contents("php://input"), true);
$nombre = $data['nombre'] ?? '';

if (!$nombre) {
  json_response(["error" => "Nombre requerido"], 400);
}

pg_query_params($conn, "INSERT INTO roles (nombre) VALUES ($1)", [$nombre]);
json_response(["ok" => true]);
