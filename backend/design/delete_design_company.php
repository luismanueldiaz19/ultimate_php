<?php

include '../conexion.php';
include '../utils.php';

$data = json_decode(file_get_contents('php://input'), true);

// Validar que venga el ID
if (!isset($data['design_company_id'])) {
    json_response([
        'success' => false,
        'message' => 'Falta design_company_id'
    ]);
    exit;
}

$designCompanyId = intval($data['design_company_id']);

// Ejecutar DELETE con parámetros
$query  = 'DELETE FROM public.design_company WHERE design_company_id = $1';
$result = pg_query_params($conn, $query, [$designCompanyId]);

if ($result && pg_affected_rows($result) > 0) {
    json_response([
        'success' => true,
        'message' => 'Registro eliminado correctamente'
    ]);
} else {
    json_response([
        'success' => false,
        'message' => 'No se encontró el registro o no se pudo eliminar'
    ]);
}
