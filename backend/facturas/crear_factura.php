<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Método no permitido'], 405);
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

$usuarioId = $data['usuario_id'] ?? null;
$tipoNcf = $data['tipo_ncf'] ?? 'B02';

if (!$usuarioId) {
    json_response(["success" => false, "message" => "Usuario no autenticado"], 401);
}

if (
    empty($data['id_cliente']) ||
    !isset($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0
) {
    json_response(["success" => false, "message" => "Datos incompletos de factura"], 400);
}

// PASO 1: Obtener y bloquear secuencia NCF
$resNCF = pg_query_params($conn, "SELECT * FROM secuencias_ncf WHERE tipo_ncf = $1 FOR UPDATE", [$tipoNcf]);
$ncfData = pg_fetch_assoc($resNCF);

if (!$ncfData) {
    json_response(["success" => false, "message" => "No existe secuencia para tipo NCF '$tipoNcf'"], 400);
}

$secuencia = str_pad($ncfData['secuencia_actual'], 8, "0", STR_PAD_LEFT);
$ncf = $ncfData['prefijo'] . $secuencia;

// // PASO 2: Calcular totales
// $total_bruto = 0;
// $total_itbis = 0;

// foreach ($data['detalles'] as $item) {
//     $linea_bruto = $item['cantidad'] * $item['precio_unitario'];
//     $linea_itbis = $linea_bruto * ($item['itbis'] / 100);
//     $total_bruto += $linea_bruto;
//     $total_itbis += $linea_itbis;
// }

$total_bruto = 0;
$total_itbis = 0;

foreach ($data['detalles'] as $item) {
    $precio_neto = $item['precio_unitario'] / (1 + ($item['itbis'] / 100));
    $linea_bruto = $precio_neto * $item['cantidad'];
    $linea_itbis = ($item['precio_unitario'] - $precio_neto) * $item['cantidad'];

    $total_bruto += $linea_bruto;
    $total_itbis += $linea_itbis;
}

$total_final = $total_bruto + $total_itbis; // o simplemente sumar los precios_unitarios * cantidad


$total_final = $total_bruto + $total_itbis;

// PASO 3: Insertar factura
$sqlFactura = "INSERT INTO facturas 
(id_cliente, ncf, tipo_ncf, fecha, total_bruto, total_itbis, total_final, estado,id_usuario)
VALUES ($1, $2, $3, NOW(), $4, $5, $6, 'ACTIVA',$7) RETURNING id_factura";

$resFactura = pg_query_params($conn, $sqlFactura, [
    $data['id_cliente'],
    $ncf,
    $tipoNcf,
    $total_bruto,
    $total_itbis,
    $total_final,
    $usuarioId,
]);

if (!$resFactura) {
    json_response(["success" => false, "message" => "Error al insertar factura: " . pg_last_error($conn)], 500);
}

$id_factura = pg_fetch_result($resFactura, 0, 'id_factura');

// PASO 4: Insertar detalles
foreach ($data['detalles'] as $item) {
    $linea_bruto = $item['cantidad'] * $item['precio_unitario'];
    $linea_itbis = $linea_bruto * ($item['itbis'] / 100);
    $linea_total = $linea_bruto;

    $resDetalle = pg_query_params($conn,
        "INSERT INTO detalle_factura 
        (id_factura, id_producto, cantidad, precio_unitario, itbis, total_linea)
        VALUES ($1, $2, $3, $4, $5, $6)",
        [
            $id_factura,
            $item['id_producto'],
            $item['cantidad'],
            $item['precio_unitario'],
            $item['itbis'],
            $linea_total
        ]
    );

    if (!$resDetalle) {
        json_response(["success" => false, "message" => "Error al insertar detalle: " . pg_last_error($conn)], 500);
    }
}

// PASO 5: Actualizar secuencia NCF
pg_query_params($conn,
    "UPDATE secuencias_ncf SET secuencia_actual = secuencia_actual + 1 WHERE tipo_ncf = $1",
    [$tipoNcf]
);


//---------------------------------
// PASO 7: Generar asiento contable
$descripcion_asiento = "Factura $ncf generada para cliente {$data['id_cliente']}";

$resAsiento = pg_query_params($conn,
  "INSERT INTO asientos_contables (id_factura, descripcion, id_usuario, total_debe, total_haber)
   VALUES ($1, $2, $3, $4, $5) RETURNING id_asiento",
  [$id_factura, $descripcion_asiento, $usuarioId, $total_final, $total_final]
);

if (!$resAsiento) {
    json_response(["success" => false, "message" => "Error al insertar asiento contable: " . pg_last_error($conn)], 500);
}

$id_asiento = pg_fetch_result($resAsiento, 0, 'id_asiento');

// Cuentas contables base (puedes moverlas a una tabla si quieres hacerlo dinámico)
$cuentaCXC = '1201';      // CUENTAS POR COBRAR CLIENTES
$cuentaVentas = '4101';   // INGRESOS POR VENTAS
$cuentaITBIS = '2102';    // ITBIS POR PAGAR

$detallesContables = [
    [$cuentaCXC, 'CUENTAS POR COBRAR CLIENTE', $total_final, 0],
    [$cuentaVentas, 'INGRESO POR VENTA', 0, $total_bruto],
    [$cuentaITBIS, 'ITBIS POR PAGAR', 0, $total_itbis],
];

foreach ($detallesContables as [$codigo, $desc, $debe, $haber]) {
    $resDetalleContable = pg_query_params($conn,
        "INSERT INTO detalle_asiento (id_asiento, cuenta_codigo, descripcion, debe, haber)
         VALUES ($1, $2, $3, $4, $5)",
        [$id_asiento, $codigo, $desc, $debe, $haber]
    );

    if (!$resDetalleContable) {
        json_response(["success" => false, "message" => "Error al insertar detalle contable: " . pg_last_error($conn)], 500);
    }
}


// PASO 6: Registrar auditoría
registrarAuditoria($conn, $usuarioId, 'INSERT', 'facturas', null, [
    "id_factura" => $id_factura,
    "ncf" => $ncf,
    "tipo_ncf" => $tipoNcf,
    "cliente" => $data['id_cliente'],
    "total_bruto" => $total_bruto,
    "total_itbis" => $total_itbis,
    "total_final" => $total_final,
    "detalles" => $data['detalles']
]);

json_response([
    "success" => true,
    "message" => "Factura registrada exitosamente",
    "ncf" => $ncf,
    "id_factura" => $id_factura,
    "total_bruto" => $total_bruto,
    "total_itbis" => $total_itbis,
    "total_final" => $total_final
]);
