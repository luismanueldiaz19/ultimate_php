<?php
include '../conexion.php';
include '../utils.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        'success' => false,
        'message' => 'Método no permitido. Solo se permite POST.'
    ]);
    exit;
}

// Obtener datos del cuerpo de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

// Verificar que date1 y date2 existan
if (!isset($data['date1']) || !isset($data['date2'])) {
    json_response([
        'success' => false,
        'message' => 'Debe proporcionar date1 y date2 en el cuerpo de la solicitud.'
    ]);
    exit;
}

// Obtener fechas y agregar rangos de tiempo si es solo la fecha
$date1 = isset($data['date1']) ? $data['date1'] . ' 00:00:00' : null;
$date2 = isset($data['date2']) ? $data['date2'] . ' 23:59:59' : null;

$date1 = pg_escape_string($conn, $date1);
$date2 = pg_escape_string($conn, $date2);


// Consulta con filtro por fecha
$query = "
    SELECT f.*, c.nombre AS cliente_nombre
    FROM facturas f
    JOIN clientes c ON f.id_cliente = c.id_cliente
    WHERE f.fecha BETWEEN '$date1' AND '$date2'
    ORDER BY f.fecha DESC
";

$res = pg_query($conn, $query);

if (!$res) {
    json_response([
        'success' => false,
        'message' => 'Error al obtener facturas: ' . pg_last_error($conn)
    ]);
}

$facturas = pg_fetch_all($res) ?? [];

json_response([
    'success' => true,
    'data' => $facturas,
    'total' => count($facturas),
]);
