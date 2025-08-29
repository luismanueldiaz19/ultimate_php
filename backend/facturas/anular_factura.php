<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

$data = json_decode(file_get_contents("php://input"), true);
$usuarioId = $data['usuario_id'] ?? null;
$idFactura = $data['id_factura'] ?? null;

if (!$usuarioId || !$idFactura) {
  json_response(["error" => "Datos incompletos"], 400);
}

// Obtener datos actuales de la factura para auditoría
$res = pg_query_params($conn, "SELECT * FROM facturas WHERE id_factura = $1", [$idFactura]);
$datos_anteriores = pg_fetch_assoc($res);

if (!$datos_anteriores) {
  json_response(["error" => "Factura no encontrada"], 404);
}

if ($datos_anteriores['estado'] === 'ANULADA') {
  json_response(["error" => "Factura ya está anulada"], 400);
}

// Actualizar estado a ANULADA
$resUpdate = pg_query_params($conn, "UPDATE facturas SET estado = 'ANULADA' WHERE id_factura = $1", [$idFactura]);

if (!$resUpdate) {
  json_response(["error" => "Error al anular factura"], 500);
}

// Registrar auditoría de anulación
registrarAuditoria($conn, $usuarioId, 'ANULAR', 'facturas', $datos_anteriores, ['estado' => 'ANULADA']);

json_response(["ok" => true, "mensaje" => "Factura anulada correctamente"]);
