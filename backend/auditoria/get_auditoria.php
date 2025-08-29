<?php
include '../conexion.php';
include '../utils.php';

$res = pg_query($conn, "
  SELECT a.*, u.username
  FROM auditoria a
  JOIN usuarios u ON a.id_usuario = u.id_usuario
  ORDER BY a.fecha DESC
");

$data = pg_fetch_all($res) ?? [];
json_response($data);
