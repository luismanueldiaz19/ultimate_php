<?php
include '../conexion.php';
include '../utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = $_POST;
if (empty($input)) {
    $input = json_decode(file_get_contents("php://input"), true);
}

if (empty($input['id_empleado'])) {
    echo json_encode(['status' => false, 'message' => 'Falta el id_empleado para eliminar']);
    exit;
}

$query = "DELETE FROM empleado WHERE id_empleado = $1";
$result = pg_query_params($conn, $query, [$input['id_empleado']]);

if (!$result) {
    echo json_encode(['status' => false, 'message' => 'Error al eliminar el empleado']);
    exit;
}

echo json_encode(['status' => true, 'message' => 'Empleado eliminado correctamente']);
pg_close($conn);