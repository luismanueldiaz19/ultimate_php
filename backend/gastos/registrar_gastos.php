<?php
include '../../connection.php';
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$input = json_decode(file_get_contents('php://input'), true);

$tipo_gasto = $input['tipo_gasto'] ?? 'Gasto Mantenimiento';
$monto      = floatval($input['monto'] ?? 0);
$descripcion= $input['descripcion'] ?? '';

// 1️⃣ Insertar asiento contable
$insert_asiento_sql = "INSERT INTO asientos_contables(fecha, descripcion, total, estado)
                       VALUES (CURRENT_DATE, $1, $2, 'CONFIRMADO') RETURNING id_asiento";
$params_asiento = [$descripcion, $monto];

$result_asiento = pg_query_params($connection, $insert_asiento_sql, $params_asiento);
$id_asiento = pg_fetch_result($result_asiento, 0, 'id_asiento');

// 2️⃣ Obtener parámetros contables del gasto
$param_sql = "SELECT codigo_cuenta, tipo_movimiento 
              FROM parametros_contables 
              WHERE nombre_operacion = $1";
$result_param = pg_query_params($connection, $param_sql, [$tipo_gasto]);

// 3️⃣ Insertar detalles del asiento
while ($row = pg_fetch_assoc($result_param)) {
    $codigo = $row['codigo_cuenta'];
    $tipo   = $row['tipo_movimiento'];
    
    $debe  = ($tipo === 'DEBE') ? $monto : 0;
    $haber = ($tipo === 'HABER') ? $monto : 0;

    $insert_detalle_sql = "INSERT INTO detalle_asiento(id_asiento, codigo_cuenta, debe, haber)
                           VALUES ($1, $2, $3, $4)";
    pg_query_params($connection, $insert_detalle_sql, [$id_asiento, $codigo, $debe, $haber]);
}

echo json_encode(['success' => true, 'message' => 'Gasto registrado correctamente', 'id_asiento' => $id_asiento]);

pg_close($connection);
?>
