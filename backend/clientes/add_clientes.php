<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

// Leer JSON del body
$data = json_decode(file_get_contents("php://input"), true);

// Lista de campos obligatorios
$requiredFields = ['nombre', 'rnc_cedula', 'tipo_entidad', 'tipo_identificacion', 'email', 'telefono', 'direccion', 'codigo_cuenta_cxc'];

// Verificar si faltan campos obligatorios
$faltantes = [];
foreach ($requiredFields as $campo) {
    if (!isset($data[$campo]) || trim($data[$campo]) === '') {
        $faltantes[] = $campo;
    }
}

if (!empty($faltantes)) {
    json_response([
        "success" => false,
        "message" => "Faltan campos obligatorios",
        "faltantes" => $faltantes
    ], 400);
    exit;
}

// Validar usuario autenticado
$usuarioId = $data['usuario_id'] ?? null;
if (!$usuarioId) {
    json_response(["success" => false, "message" => "Usuario no autenticado."], 401);
    exit;
}

// Campos opcionales con valores por defecto
$tiene_credito   = isset($data['tiene_credito']) ? $data['tiene_credito'] : false;
$limite_credito  = $data['limite_credito'] ?? 100000;
$dias_credito    = $data['dias_credito'] ?? 30;

// Inserción del cliente
$sql = "
    INSERT INTO public.clientes 
    (nombre, rnc_cedula, tipo_entidad, tipo_identificacion, email, telefono, direccion, tiene_credito, limite_credito, dias_credito, codigo_cuenta_cxc)
    VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
";

$params = [
    trim($data['nombre']),
    trim($data['rnc_cedula']),
    strtoupper(trim($data['tipo_entidad'])),
    strtoupper(trim($data['tipo_identificacion'])),
    trim($data['email']),
    trim($data['telefono']),
    trim($data['direccion']),
    $tiene_credito,
    $limite_credito,
    $dias_credito,
    trim($data['codigo_cuenta_cxc'])
];

$result = pg_query_params($conn, $sql, $params);

// Verificar éxito de la inserción
if (!$result) {
    json_response(["success" => false, "message" => "Error al guardar el cliente: " . pg_last_error($conn)], 500);
    exit;
}

// Auditoría
registrarAuditoria(
    $conn,
    $usuarioId,
    'INSERT',
    'clientes',
    null,
    $data
);

// OK
json_response([
    "success" => true,
    "message" => "Cliente creado exitosamente."
]);
