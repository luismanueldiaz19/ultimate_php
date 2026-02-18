<?php

include '../conexion.php';
include '../utils.php';

header('Content-Type: application/json; charset=UTF-8');

$data = json_decode(file_get_contents('php://input'), true);

// Validaciones obligatorias
if (empty($data['institution_name'])) {
    json_response(['success' => false, 'message' => 'Falta institution_name'], 400);
}

if (empty($data['registed_by'])) {
    json_response(['success' => false, 'message' => 'Falta registed_by (registed_by)'], 400);
}

$institution_name = trim($data['institution_name']);
$registed_by      = $data['registed_by'];
$cliente          = $data['cliente'] ?? null; // opcional

pg_query($conn, 'BEGIN');

try {

    $sql = 'INSERT INTO design_company 
            (institution_name, cliente, registed_by)
            VALUES ($1, $2, $3)
            RETURNING *';

    $params = [
        $institution_name,
        $cliente,      // puede ser null
        $registed_by
    ];

    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        throw new Exception('Existe Este Nombre Institucion/logo/clientes');
    }

    $row = pg_fetch_assoc($result);

    pg_query($conn, 'COMMIT');

    json_response([
        'success' => true,
        'message' => 'Registro insertado correctamente',
        'data'    => $row
    ]);

} catch (Exception $e) {
    pg_query($conn, 'ROLLBACK');
    json_response([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
