<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");

$data = json_decode(file_get_contents("php://input"), true);

// Validaciones básicas
if (empty($data['design_company_id']) || empty($data['tipo_trabajo'])) {
    json_response(["success" => false, "message" => "Faltan campos obligatorios"], 400);
}

$usuarioId = $data['usuario_id'] ?? null;
if (!$usuarioId) {
    json_response(["success" => false, "message" => "Usuario no autenticado"], 401);
}

pg_query($conn, "BEGIN");

try {
    $sql = "INSERT INTO public.design_tipo (design_company_id, tipo_trabajo) VALUES ($1, $2) RETURNING *";
    $params = [
        $data['design_company_id'],
        $data['tipo_trabajo']
    ];

    $result = @pg_query_params($conn, $sql, $params);
    if (!$result) {
        throw new Exception("Error al insertar en design_tipo.");
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
    json_response(["success" => false, "message" => $e->getMessage()], 500);
}