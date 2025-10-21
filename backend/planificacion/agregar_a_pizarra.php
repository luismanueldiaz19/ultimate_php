<?php
include '../conexion.php';
include '../utils.php';
header("Content-Type: application/json");

try {
    $data = json_decode(file_get_contents("php://input"), true);

    $item_pre_orden_id = $data['item_pre_orden_id'] ?? null;
    $planificacion_work_id = $data['planificacion_work_id'] ?? null;
    $name_department = $data['name_department'] ?? null;
    $fecha_planificacion = $data['fecha_planificacion'] ?? date('Y-m-d');
    $comentario_planificador = $data['comentario_planificador'] ?? null;

    if (!$item_pre_orden_id || !$planificacion_work_id || !$name_department) {
        json_response(["success" => false, "message" => "Faltan parámetros obligatorios."]);
        exit;
    }

    // 🚀 Iniciar transacción
    pg_query($conn, "BEGIN");

    // 1️⃣ Verificar si ya existe el item_pre_orden_id en planificador
    $checkSql = "SELECT COUNT(*) AS total FROM planificador WHERE item_pre_orden_id = $1";
    $checkResult = pg_query_params($conn, $checkSql, [$item_pre_orden_id]);
    $exists = pg_fetch_assoc($checkResult)['total'] ?? 0;

    if ($exists > 0) {
        pg_query($conn, "ROLLBACK");
        json_response(["success" => false, "message" => "El item_pre_orden_id ya está planificado."]);
        exit;
    }

    // 2️⃣ Obtener el index_plan actual del departamento
    $indexSql = "SELECT index_plan FROM departments WHERE name_department = $1 FOR UPDATE";
    $indexResult = pg_query_params($conn, $indexSql, [$name_department]);
    $row = pg_fetch_assoc($indexResult);
    $currentIndex = $row['index_plan'] ?? 0;
    $newIndex = $currentIndex + 1;

    // 3️⃣ Actualizar el index_plan del departamento
    $updateDeptSql = "UPDATE departments SET index_plan = $1 WHERE name_department = $2";
    $updateDept = pg_query_params($conn, $updateDeptSql, [$newIndex, $name_department]);
    if (!$updateDept) {
        throw new Exception("Error al actualizar el índice del departamento.");
    }

    // 4️⃣ Insertar en planificador
    $insertSql = "INSERT INTO planificador (
        item_pre_orden_id, planificacion_work_id, name_department,
        index_panificacion, fecha_planificacion, comentario_planificador
    ) VALUES ($1, $2, $3, $4, $5, $6) RETURNING planificador_id";

    $insertResult = pg_query_params($conn, $insertSql, [
        $item_pre_orden_id, $planificacion_work_id, $name_department,
        $newIndex, $fecha_planificacion, $comentario_planificador
    ]);

    if (!$insertResult) {
        throw new Exception("Error al insertar el planificador.");
    }

    $planificador = pg_fetch_assoc($insertResult);

    // 5️⃣ Actualizar is_planned en planificacion_work
    $updateWorkSql = "UPDATE planificacion_work SET is_planned = true WHERE planificacion_work_id = $1";
    $updateWork = pg_query_params($conn, $updateWorkSql, [$planificacion_work_id]);
    if (!$updateWork) {
        throw new Exception("Error al actualizar planificacion_work.");
    }

    // ✅ Confirmar transacción
    pg_query($conn, "COMMIT");

    json_response([
        "success" => true,
        "message" => "Planificación registrada exitosamente.",
        "data" => $planificador
    ]);

} catch (Exception $e) {
    // ❌ Revertir cambios en caso de error
    pg_query($conn, "ROLLBACK");
    json_response(["success" => false, "message" => $e->getMessage()]);
}
?>
