<?php
include '../conexion.php';
include '../utils.php';
header("Content-Type: application/json");

// Función para generar número de nota robusto
function generarNumeroNota($conn) {
    $prefijo = 'NC';
    $fechaHoy = date('Ymd');

    $sql = "SELECT COUNT(*) FROM notas_credito WHERE TO_CHAR(fecha_emision, 'YYYYMMDD') = $1";
    $res = pg_query_params($conn, $sql, [$fechaHoy]);

    if (!$res) {
        throw new Exception(pg_last_error($conn));
    }

    $conteo = pg_fetch_result($res, 0, 0);
    $secuencia = str_pad($conteo + 1, 3, '0', STR_PAD_LEFT);

    return $prefijo . '-' . $fechaHoy . '-' . $secuencia;
}

// Leer datos JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validar campos principales
$camposObligatorios = ['pre_orden_id', 'id_cliente', 'id_usuario', 'motivo', 'total', 'items'];
$faltantes = [];

foreach ($camposObligatorios as $campo) {
    if (!isset($data[$campo]) || 
        (is_string($data[$campo]) && trim($data[$campo]) === '') || 
        $data[$campo] === null
    ) {
        $faltantes[] = $campo;
    }
}

// Validar que items sea array no vacío
if (!isset($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
    $faltantes[] = 'items';
}

if (!empty($faltantes)) {
    echo json_encode([
        'status' => false,
        'message' => 'Faltan campos requeridos o están vacíos',
        'faltantes' => $faltantes
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar cada item
foreach ($data['items'] as $index => $item) {
    $camposItem = ['id_producto', 'descripcion', 'cantidad', 'precio_unitario', 'subtotal'];
    foreach ($camposItem as $campo) {
        if (!isset($item[$campo]) || $item[$campo] === '' || $item[$campo] === null) {
            echo json_encode([
                'status' => false,
                'message' => "Falta el campo '$campo' en el item #" . ($index + 1)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

try {
    pg_query($conn, "BEGIN");

    $numeroNota = generarNumeroNota($conn);

    // Insertar nota de crédito
    $sqlNota = "INSERT INTO notas_credito (
        numero_nota, fecha_emision, pre_orden_id, id_cliente, motivo, total, estado, creado_por
    ) VALUES (
        $1, CURRENT_DATE, $2, $3, $4, $5, 'emitida', $6
    ) RETURNING notas_credito_id";

    $paramsNota = [
        $numeroNota,
        $data['pre_orden_id'],
        $data['id_cliente'],
        $data['motivo'],
        $data['total'],
        $data['id_usuario']
    ];

    $resNota = pg_query_params($conn, $sqlNota, $paramsNota);
    if (!$resNota) {
        throw new Exception(pg_last_error($conn));
    }

    $notaId = pg_fetch_result($resNota, 0, 'notas_credito_id');

    // Insertar detalle
    $sqlDetalle = "INSERT INTO detalle_nota_credito (
        nota_credito_id, id_producto, descripcion, cantidad, precio_unitario, subtotal
    ) VALUES ($1, $2, $3, $4, $5, $6)";

    foreach ($data['items'] as $item) {
        $paramsDetalle = [
            $notaId,
            $item['id_producto'],
            $item['descripcion'],
            $item['cantidad'],
            $item['precio_unitario'],
            $item['subtotal']
        ];

        $resDetalle = pg_query_params($conn, $sqlDetalle, $paramsDetalle);
        if (!$resDetalle) {
            throw new Exception(pg_last_error($conn));
        }
    }

    pg_query($conn, "COMMIT");

    echo json_encode([
        'status' => true,
        'message' => 'Nota de crédito registrada correctamente',
        'nota_credito_id' => $notaId,
        'numero_nota' => $numeroNota
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    echo json_encode([
        'status' => false,
        'message' => 'Error al registrar la nota de crédito',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>