<?php
include '../conexion.php';
include '../utils.php';

$query = "SELECT * FROM clientes ORDER BY creado_en DESC";

$result = pg_query($conn, $query);

if (!$result) {
    json_response([
        'success' => false,
        'message' => 'Error al consultar clientes.',
    ], 500);
}

$clientes = pg_fetch_all($result) ?? [];

json_response([
    'success' => true,
    'data' => $clientes,
]);
