<?php
include '../conexion.php'; // Asegúrate de tener $conn configurado
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['plantilla_id'])) {
    echo json_encode([
        'status' => false,
        'message' => 'plantilla_id no recibido'
    ]);
    exit;
}

$plantilla_id = intval($data['plantilla_id']);

try {
    // 1. Verificar si existe la plantilla
    $sql_check = "SELECT plantilla_id FROM plantilla_contable WHERE plantilla_id = $1";
    $res_check = pg_query_params($conn, $sql_check, [$plantilla_id]);

    if (!$res_check) {
        throw new Exception(pg_last_error($conn));
    }

    if (pg_num_rows($res_check) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'La plantilla no existe',
            'plantilla_id' => $plantilla_id
        ]);
        exit;
    }

    // 2. Eliminar dentro de transacción
    pg_query($conn, "BEGIN");

    $sql_delete = "DELETE FROM plantilla_contable WHERE plantilla_id = $1";
    $res_delete = pg_query_params($conn, $sql_delete, [$plantilla_id]);

    if (!$res_delete) {
        throw new Exception(pg_last_error($conn));
    }

    pg_query($conn, "COMMIT");

    echo json_encode([
        'success' => true,
        'message' => 'Plantilla eliminada correctamente',
        'plantilla_id' => $plantilla_id
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");

    echo json_encode([
        'status' => false,
        'message' => 'Error al eliminar la plantilla',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>