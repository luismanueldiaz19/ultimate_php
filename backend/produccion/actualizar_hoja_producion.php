<?php
include '../conexion.php';
include '../utils.php';

$data = json_decode(file_get_contents('php://input'), true);

// Validaciones
$start_date = $data['start_date'] ?? null;
$end_date = $data['end_date'] ?? null;
$estado_hoja_producion = $data['estado_hoja_producion'] ?? null;
$comentario_producion = $data['comentario_producion'] ?? null;
$hoja_produccion_id = $data['hoja_produccion_id'] ?? null;




$required_fields = [
    'start_date',
    'estado_hoja_producion',
    'comentario_producion',
    'hoja_produccion_id'
];

$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        $missing_fields[] = $field;
    }
}

if (count($missing_fields) > 0) {
    json_response([
        'success' => false,
        'message' => 'Faltan campos requeridos',
        'missing_fields' => $missing_fields
    ]);
    exit;
}

pg_query($conn, "BEGIN");

$sql_update_hoja = "UPDATE public.hoja_produccion 
                    SET start_date = $1, 
                        end_date = $2, 
                        estado_hoja_producion = $3, 
                        comentario_producion = $4 
                    WHERE hoja_produccion_id = $5";

$params = [$start_date, $end_date, $estado_hoja_producion, $comentario_producion, $hoja_produccion_id];
$res_update_hoja = pg_query_params($conn, $sql_update_hoja, $params);

if (!$res_update_hoja) {
    pg_query($conn, "ROLLBACK");
    json_response(['success' => false, 'message' => 'Error actualizando hoja_produccion']);
    exit;
}

pg_query($conn, "COMMIT");
json_response(['success' => true, 'message' => 'Hoja de producción actualizada correctamente']);