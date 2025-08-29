<?php
include '../conexion.php';
include '../utils.php';

$res = pg_query($conn, "
  SELECT p.*, r.nombre AS rol
  FROM permisos p
  JOIN roles r ON p.rol_id = r.id_rol
");
$data = pg_fetch_all($res) ?? [];

json_response($data);
