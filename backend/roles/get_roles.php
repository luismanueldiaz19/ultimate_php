<?php
include '../conexion.php';
include '../utils.php';

$result = pg_query($conn, "SELECT * FROM roles ORDER BY nombre");
$roles = pg_fetch_all($result) ?? [];

json_response($roles);
