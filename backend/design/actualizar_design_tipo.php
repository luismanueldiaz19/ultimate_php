<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");

$data = json_decode(file_get_contents("php://input"), true);

// Validaciones básicas
if (empty($data['design_tipo_id']) || empty($data['costo_logo']) || empty($data['has_cost'])) {
    json_response(["success" => false, "message" => "Faltan campos obligatorios"], 400);
}

// $usuarioId = $data['usuario_id'] ?? null;

// if (!$usuarioId) {
//     json_response(["success" => false, "message" => "Usuario no autenticado"], 401);
// }

pg_query($conn, "BEGIN");

try {
    $sql = "UPDATE public.design_tipo
            SET facturado_por = $1,
                costo_logo = $2,
                fecha_facturado = $3,
                has_cost = $4,
                estado_aprobacion_id = $5
            WHERE design_tipo_id = $6
            RETURNING *";

    $params = [
        $data['facturado_por'] ?? null,
        $data['costo_logo'],
        $data['fecha_facturado'] ?? null,
        $data['has_cost'],
        $data['estado_aprobacion_id'] ?? null,
        $data['design_tipo_id']
    ];

    $result = @pg_query_params($conn, $sql, $params);
    if (!$result) {
        throw new Exception("Error al actualizar design_tipo.");
    }

    $updated = pg_fetch_assoc($result);

    pg_query($conn, "COMMIT");

    json_response([
        "success" => true,
        "message" => "Registro actualizado correctamente",
        "data" => $updated
    ]);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    json_response(["success" => false, "message" => $e->getMessage()], 500);
}