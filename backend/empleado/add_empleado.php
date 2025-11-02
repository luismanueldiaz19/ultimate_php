<?php
include '../conexion.php';
include '../utils.php';

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Método no permitido']);
    exit;
}

// Leer datos (soporta JSON o form-data)
$input = $_POST;
if (empty($input)) {
    $input = json_decode(file_get_contents("php://input"), true);
}

// Campos requeridos
$requeridos = [
    'codigo', 'nombre', 'apellido', 'cedula', 'fecha_nacimiento',
    'direccion', 'telefono', 'correo', 'fecha_ingreso',
    'department_id', 'id_cargo', 'salario_base'
];

$faltantes = [];
foreach ($requeridos as $campo) {
    if (!isset($input[$campo]) || trim($input[$campo]) === '') {
        $faltantes[] = $campo;
    }
}

if (!empty($faltantes)) {
    echo json_encode([
        'status' => false,
        'message' => 'Faltan campos requeridos',
        'faltantes' => $faltantes
    ]);
    exit;
}

// Valores opcionales
$estado = $input['estado'] ?? 'activo';
$turno = $input['turno'] ?? 'A';
$metodo_ponche = $input['metodo_ponche'] ?? 'manual';
$foto_url = $input['foto_url'] ?? 'N/A';

// Consulta
$query = "INSERT INTO empleado (
    codigo, nombre, apellido, cedula, fecha_nacimiento, direccion,
    telefono, correo, fecha_ingreso, estado, department_id, id_cargo,
    turno, salario_base, metodo_ponche, foto_url
) VALUES (
    $1, $2, $3, $4, $5, $6,
    $7, $8, $9, $10, $11, $12,
    $13, $14, $15, $16
) RETURNING id_empleado";

$params = [
    $input['codigo'],
    $input['nombre'],
    $input['apellido'],
    $input['cedula'],
    $input['fecha_nacimiento'],
    $input['direccion'],
    $input['telefono'],
    $input['correo'],
    $input['fecha_ingreso'],
    $estado,
    $input['department_id'],
    $input['id_cargo'],
    $turno,
    $input['salario_base'],
    $metodo_ponche,
    $foto_url
];

$result = pg_query_params($conn, $query, $params);

if (!$result) {
    echo json_encode([
        'status' => false,
        'message' => 'Error al insertar el empleado'
    ]);
    exit;
}

$inserted = pg_fetch_assoc($result);

echo json_encode([
    'status' => true,
    'message' => 'Empleado registrado correctamente',
    'id_empleado' => $inserted['id_empleado']
]);

pg_free_result($result);
pg_close($conn);