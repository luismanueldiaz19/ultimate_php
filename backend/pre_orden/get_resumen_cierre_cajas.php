<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

include '../conexion.php';
include '../utils.php';
header("Content-Type: application/json");

// Leer y validar entrada
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$fields = [
    'date1' => trim($input['date1'] ?? ''),
    'date2' => trim($input['date2'] ?? ''),
    'id_usuario' => trim($input['id_usuario'] ?? ''),
];

// Validar campos obligatorios
$required = ['id_usuario', 'date1', 'date2'];
$empty_fields = array_filter($required, fn($f) => empty($fields[$f]));

if (!empty($empty_fields)) {
    echo json_encode([
        'success' => false,
        'message' => 'Campos obligatorios incompletos',
        'empty_fields' => array_values($empty_fields)
    ]);
    exit;
}

// Validar formato de fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fields['date1']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fields['date2'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Formato de fecha inválido. Use YYYY-MM-DD.'
    ]);
    exit;
}

// Consulta SQL parametrizada
$sql = "SELECT 
    p.id_usuario,
    -- Totales por tipo de pago
    SUM(CASE WHEN p.metodo_pago = 'efectivo' THEN p.monto_pago ELSE 0 END) AS total_efectivo,
    SUM(CASE WHEN p.metodo_pago = 'tarjeta' THEN p.monto_pago ELSE 0 END) AS total_tarjeta,
    SUM(CASE WHEN p.metodo_pago = 'transferencia' THEN p.monto_pago ELSE 0 END) AS total_transferencia,
    SUM(CASE WHEN p.metodo_pago = 'cheque' THEN p.monto_pago ELSE 0 END) AS total_cheque,
    SUM(CASE WHEN p.metodo_pago = 'nota_credito' THEN p.monto_pago ELSE 0 END) AS total_nota_credito,
    SUM(p.monto_pago) AS total_general,

    -- Rango de secuencia (min y max de num_orden)
    MIN(o.num_orden) AS secuencia_inicial,
    MAX(o.num_orden) AS secuencia_final
FROM pagos_pre_orden p
JOIN pre_orden o ON o.pre_orden_id = p.pre_orden_id
WHERE p.id_usuario = $1 AND DATE(p.fecha_pago) BETWEEN $2 AND $3
GROUP BY p.id_usuario;";

$params = [$fields['id_usuario'], $fields['date1'], $fields['date2']];

try {
    $res = pg_query_params($conn, $sql, $params);
    if (!$res) {
        throw new Exception(pg_last_error($conn));
    }

    $resumen = pg_fetch_assoc($res) ?: [];

    if (!$resumen) {
    echo json_encode([
        'success' => false,
        'message' => 'No se encontraron resultados en el rango de fechas especificado.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
    echo json_encode([
        'success' => true,
        'data' => $resumen
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error en consulta: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al ejecutar la consulta',
        'error' => $e->getMessage()
    ]);
}
?>