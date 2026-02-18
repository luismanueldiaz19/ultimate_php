<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");

$data = json_decode(file_get_contents("php://input"), true);

// 🔍 Campos requeridos
$required_fields = [
    'design_company_id',
    'tipo_trabajo',
    'costo_logo',
    'has_cost',
    'duracion'
];

// Verificar cuáles faltan o están vacíos
$missing_fields = [];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    json_response([
        "success" => false,
        "message" => "Faltan campos obligatorios",
        "missing_fields" => $missing_fields
    ], 400);
}

$usuarioId = $data['usuario_id'] ?? null;
if (!$usuarioId) {
    json_response([
        "success" => false,
        "message" => "Usuario no autenticado"
    ], 401);
}

pg_query($conn, "BEGIN");

try {
    // Buscar el estado "Pendiente"
    $estadoResult = pg_query_params($conn,
        "SELECT id FROM public.estado_aprobacion WHERE nombre_estado = $1 LIMIT 1",
        ['Pendiente']
    );

    if (!$estadoResult || pg_num_rows($estadoResult) === 0) {
        throw new Exception("Estado 'Pendiente' no encontrado.");
    }

    $estado = pg_fetch_assoc($estadoResult);
    $estadoId = $estado['id'];

    // Insertar en design_tipo
    $sql = "INSERT INTO public.design_tipo(design_company_id, tipo_trabajo, costo_logo, has_cost, duracion, estado_aprobacion_id
            ) VALUES ($1, $2, $3, $4, $5, $6)
            RETURNING *";

    $params = [
        $data['design_company_id'],
        $data['tipo_trabajo'],
        $data['costo_logo'],
        $data['has_cost'],
        $data['duracion'],
        $estadoId
    ];

    $result = pg_query_params($conn, $sql, $params);
    if (!$result) {
        throw new Exception("Error al insertar en design_tipo: " . pg_last_error($conn));
    }

    $inserted = pg_fetch_assoc($result);
    pg_query($conn, "COMMIT");

    json_response([
        "success" => true,
        "message" => "Registro insertado correctamente",
        "data" => $inserted
    ]);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    json_response([
        "success" => false,
        "message" => $e->getMessage()
    ], 500);
}
?>
