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
    echo json_encode(['status' => false, 'message' => 'Falta el id_empleado para actualizar']);
    exit;
}

// Campos que se pueden actualizar
$campos = [
    'codigo', 'nombre', 'apellido', 'cedula', 'fecha_nacimiento',
    'direccion', 'telefono', 'correo', 'fecha_ingreso',
    'estado', 'department_id', 'id_cargo', 'turno',
    'salario_base', 'metodo_ponche', 'foto_url'
];

$set = [];
$params = [];
$i = 1;

foreach ($campos as $campo) {
    if (isset($input[$campo])) {
        $set[] = "$campo = \$$i";
        $params[] = $input[$campo];
        $i++;
    }
}

if (empty($set)) {
    echo json_encode(['status' => false, 'message' => 'No se proporcionaron campos para actualizar']);
    exit;
}

$params[] = $input['id_empleado'];
$query = "UPDATE empleado SET " . implode(', ', $set) . " WHERE id_empleado = \$$i";

$result = pg_query_params($conn, $query, $params);

if (!$result) {
    echo json_encode(['status' => false, 'message' => 'Error al actualizar el empleado']);
    exit;
}

echo json_encode(['status' => true, 'message' => 'Empleado actualizado correctamente']);
pg_close($conn);