<?php
// File: get_all_secuencias.php
include '../conexion.php';
include '../utils.php';

$query = pg_query($conn, "SELECT * FROM secuencias_ncf ORDER BY tipo_ncf");
$rows = pg_fetch_all($query) ?? [];

json_response([
  'success' => true,
  'data' => $rows
]);