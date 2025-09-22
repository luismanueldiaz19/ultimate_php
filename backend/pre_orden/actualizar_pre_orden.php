<?php
include '../conexion.php';
include '../utils.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    // Validaciones básicas
    if (!isset($data['estado_general'], $data['nota_orden'], $data['pre_orden_id'])) {
        throw new Exception("Faltan campos requeridos.");
    }

    $estado = $data['estado_general'];
    $nota = $data['nota_orden'];
    $id = $data['pre_orden_id'];

    $query = "
        UPDATE public.pre_orden
        SET estado_general = $1, nota_orden = $2
        WHERE pre_orden_id = $3
    ";

    $result = pg_query_params($conn, $query, [$estado, $nota, $id]);

    if (!$result) {
        throw new Exception("No se pudo actualizar la orden.");
    }

    json_response([
        'success' => true,
        'message' => 'Orden actualizada correctamente.',
    ]);
} catch (Exception $e) {
    json_response([
        'success' => false,
        'message' => 'Error al actualizar la orden.',
        'error' => $e->getMessage(),
    ], 500);
}