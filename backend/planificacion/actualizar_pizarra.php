<?php
include '../conexion.php';
include '../utils.php';
header("Content-Type: application/json");

try {
    $data = json_decode(file_get_contents("php://input"), true);

    $planificador_id = $data['planificador_id'] ?? null;
    $estado_planificador = $data['estado_planificador'] ?? null;
    $comentario_planificador = $data['comentario_planificador'] ?? null;

    if (!$planificador_id || !$estado_planificador) {
        json_response([
            "success" => false,
            "message" => "Faltan parámetros obligatorios: planificador_id y estado_planificador."
        ]);
        exit;
    }

    // 🚀 Iniciar transacción
    pg_query($conn, "BEGIN");

    // 1️⃣ Verificar existencia del planificador_id
    $checkSql = "SELECT 1 FROM planificador WHERE planificador_id = $1";
    $checkResult = pg_query_params($conn, $checkSql, [$planificador_id]);

    if (pg_num_rows($checkResult) === 0) {
        pg_query($conn, "ROLLBACK");
        json_response([
            "success" => false,
            "message" => "El planificador_id $planificador_id no existe."
        ]);
        exit;
    }

    // 2️⃣ Ejecutar UPDATE
    $updateSql = "UPDATE planificador 
                  SET estado_planificador = $1, comentario_planificador = $2 
                  WHERE planificador_id = $3";

    $updateResult = pg_query_params($conn, $updateSql, [
        $estado_planificador,
        $comentario_planificador,
        $planificador_id
    ]);

    if (!$updateResult) {
        throw new Exception("Error al actualizar el estado del planificador.");
    }

    // ✅ Confirmar transacción
    pg_query($conn, "COMMIT");

    json_response([
        "success" => true,
        "message" => "Estado actualizado correctamente.",
        "data" => [
            "planificador_id" => $planificador_id,
            "estado_planificador" => $estado_planificador,
            "comentario_planificador" => $comentario_planificador
        ]
    ]);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    json_response([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>