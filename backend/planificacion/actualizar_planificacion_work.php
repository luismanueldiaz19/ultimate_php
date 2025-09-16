<?php
include '../conexion.php';
include '../utils.php';
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");


$data = json_decode(file_get_contents('php://input'), true);

// Validación de campos requeridos
$estado_planificacion_work = isset($data['estado_planificacion_work']) ? trim($data['estado_planificacion_work']) : null;
$comentario_work = isset($data['comentario_work']) ? trim($data['comentario_work']) : null;
$planificacion_work_id = isset($data['planificacion_work_id']) ? $data['planificacion_work_id'] : null;

$missing_fields = [];

if (empty($estado_planificacion_work)) $missing_fields[] = 'estado_planificacion_work';
if (empty($comentario_work)) $missing_fields[] = 'comentario_work';
if (empty($planificacion_work_id)) $missing_fields[] = 'planificacion_work_id';

if (count($missing_fields) > 0) {
    json_response([
        'success' => false,
        'message' => 'Faltan campos requeridos',
        'missing_fields' => $missing_fields
    ]);
    exit;
}

// Transacción segura
pg_query($conn, "BEGIN");

$sql = "UPDATE public.planificacion_work 
        SET estado_planificacion_work = $1, 
            comentario_work = $2 
        WHERE planificacion_work_id = $3";

$params = [$estado_planificacion_work, $comentario_work, $planificacion_work_id];
$res = pg_query_params($conn, $sql, $params);

if (!$res) {
    pg_query($conn, "ROLLBACK");
    json_response(['success' => false, 'message' => 'Error actualizando planificacion_work']);
    exit;
}

pg_query($conn, "COMMIT");
json_response(['success' => true, 'message' => 'Planificación actualizada correctamente']);