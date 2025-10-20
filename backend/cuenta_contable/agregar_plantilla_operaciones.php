<?php
include '../conexion.php'; // Asegúrate de tener $conn configurado
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['nombre_operacion']) || !isset($data['cuentas'])) {
    echo json_encode([
        'status' => false,
        'message' => 'JSON inválido o incompleto'
    ]);
    exit;
}

$nombre_operacion = $data['nombre_operacion'];
$descripcion = $data['descripcion'] ?? '';
$cuentas = $data['cuentas'];

pg_query($conn, "BEGIN");

try {
    // 1. Insertar en plantilla_contable
    $sql_plantilla = "INSERT INTO plantilla_contable (nombre_operacion, descripcion) VALUES ($1, $2) RETURNING plantilla_id";
    $res_plantilla = pg_query_params($conn, $sql_plantilla, [$nombre_operacion, $descripcion]);

    if (!$res_plantilla) {
        throw new Exception(pg_last_error($conn));
    }

    $row = pg_fetch_assoc($res_plantilla);
    $plantilla_id = $row['plantilla_id'];

    // 2. Insertar cuentas asociadas
    $sql_cuenta = "INSERT INTO plantilla_cuentas (
        plantilla_id, codigo_cuenta, tipo_movimiento, porcentaje, monto_fijo, orden
    ) VALUES ($1, $2, $3, $4, $5, $6)";

    foreach ($cuentas as $c) {
        $params = [
            $plantilla_id,
            $c['codigo_cuenta'],
            $c['tipo_movimiento'],
            $c['porcentaje'],
            $c['monto_fijo'],
            $c['orden']
        ];
        $res_cuenta = pg_query_params($conn, $sql_cuenta, $params);

        if (!$res_cuenta) {
            throw new Exception(pg_last_error($conn));
        }
    }

    pg_query($conn, "COMMIT");

    echo json_encode([
        'success' => true,
        'message' => 'Plantilla y cuentas insertadas correctamente',
        'plantilla_id' => $plantilla_id
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");

    echo json_encode([
        'success' => false,
        'message' => 'Error al insertar plantilla o cuentas',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>