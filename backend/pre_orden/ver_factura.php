<?php
include '../conexion.php';
include '../utils.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        "success" => false,
        "message" => "Método no permitido. Solo POST."
    ], 405);
    exit;
}

// Obtener JSON del cuerpo
$data = json_decode(file_get_contents("php://input"), true);

// Validar id_factura
$idFactura = $data['id_factura'] ?? null;
if (!$idFactura) {
    json_response([
        "success" => false,
        "message" => "Debe enviar id_factura en el cuerpo de la solicitud."
    ], 400);
    exit;
}

// Consulta datos factura + cliente
$sqlFactura = "SELECT
  f.id_factura,
  f.id_cliente,
  c.nombre,
  c.rnc_cedula,
  c.tipo_entidad,
  c.tipo_identificacion,
  c.email,
  c.telefono,
  c.direccion,
  c.creado_en AS cliente_creado_en,
  f.fecha,
  f.tipo_ncf,
  f.ncf,
  f.total_bruto,
  f.total_itbis,
  f.total_final,
  f.estado,
  u.id_usuario,
  u.nombre AS usuario_nombre,
  u.username
FROM public.facturas f
LEFT JOIN public.clientes c ON f.id_cliente = c.id_cliente
LEFT JOIN public.usuarios u ON f.id_usuario = u.id_usuario
WHERE f.id_factura = $1";

$resFactura = pg_query_params($conn, $sqlFactura, [$idFactura]);
$factura = pg_fetch_assoc($resFactura);

if (!$factura) {
    json_response([
        "success" => false,
        "message" => "Factura no encontrada."
    ], 404);
    exit;
}

// Consulta detalles factura
$sqlDetalles = "
SELECT 
  df.*, 
  p.id_producto,
  p.nombre AS producto_nombre,
  p.descripcion,
  p.precio,
  p.itbis,
  p.tipo,
  p.creado_en AS producto_creado_en,
  p.precio_mayor,
  p.precio_oferta,
  p.precio_especial,
  p.codigo
FROM detalle_factura df
LEFT JOIN productos p ON df.id_producto = p.id_producto
WHERE df.id_factura = $1
";

$resDetalles = pg_query_params($conn, $sqlDetalles, [$idFactura]);
$detalles = pg_fetch_all($resDetalles) ?: [];

json_response([
    "success" => true,
    "factura" => $factura,
    "detalles" => $detalles
]);
