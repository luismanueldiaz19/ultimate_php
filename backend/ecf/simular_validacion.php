<?php
include '../conexion.php';
include '../utils.php';

$data = json_decode(file_get_contents("php://input"), true);
$idFactura = $data['id_factura'] ?? null;

if (!$idFactura) {
  json_response(["error" => "Falta id_factura"], 400);
}

// Obtener datos de la factura y cliente
$sql = "
SELECT 
  f.*, 
  c.rnc_cedula, 
  c.tipo_identificacion
FROM facturas f
LEFT JOIN clientes c ON f.id_cliente = c.id_cliente
WHERE f.id_factura = $1
";

$res = pg_query_params($conn, $sql, [$idFactura]);
$factura = pg_fetch_assoc($res);

if (!$factura) {
  json_response(["error" => "Factura no encontrada"], 404);
}

$errores = [];

// Validar NCF
if (strlen($factura['ncf']) !== 11) {
  $errores[] = "NCF debe tener 11 caracteres.";
}

// Validar tipo NCF
if (!in_array($factura['tipo_ncf'], ['B01', 'B02', 'E31', 'E32'])) {
  $errores[] = "Tipo de NCF no válido.";
}

// Validar RNC/Cédula
$rnc = preg_replace('/[^0-9]/', '', $factura['rnc_cedula']);
if (strlen($rnc) !== 9 && strlen($rnc) !== 11) {
  $errores[] = "RNC o Cédula no válido.";
}

// Validar totales
$recalculado = floatval($factura['total_bruto']) + floatval($factura['total_itbis']);
if (abs($factura['total_final'] - $recalculado) > 0.01) {
  $errores[] = "Total final no cuadra con bruto + ITBIS.";
}

// Validar estado
if ($factura['estado'] !== 'ACTIVA') {
  $errores[] = "Factura no está activa.";
}

// Validar fecha
if (strtotime($factura['fecha']) > time()) {
  $errores[] = "La fecha del comprobante es futura.";
}

// Resultado final
if (count($errores) > 0) {
  json_response([
    "estado_ecf" => "RECHAZADO",
    "errores" => $errores
  ]);
} else {
  json_response([
    "estado_ecf" => "VALIDO",
    "mensaje" => "El comprobante pasa las validaciones locales."
  ]);
}
