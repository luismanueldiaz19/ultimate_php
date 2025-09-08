<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!is_array($data) || empty($data)) {
        json_response([
            "success" => false,
            "message" => "Se esperaba una lista de planificaciones",
        ], 400);
    }
    $resultados = [];
    $todosExitosos = true;
    $itemPreOrdenIdGlobal = null;

    foreach ($data as $index => $item) {
        $requiredFields = ['item_pre_orden_id', 'department_id', 'design_image_id', 'id_producto', 'comentario_work'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($item[$field]) || trim($item[$field]) === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $resultados[] = [
                "index" => $index,
                "success" => false,
                "message" => "Faltan campos obligatorios",
                "faltantes" => $missingFields
            ];
            $todosExitosos = false;
            continue;
        }

        $itemId = intval($item['item_pre_orden_id']);
        $departmentId = trim($item['department_id']);
        $designImageId = intval($item['design_image_id']);
        $productoId = intval($item['id_producto']);
        $comentario = trim($item['comentario_work']);

        // Guardar el item_pre_orden_id para el UPDATE final
        if ($itemPreOrdenIdGlobal === null) {
            $itemPreOrdenIdGlobal = $itemId;
        }

        $checkSql = "SELECT 1 FROM planificacion_work WHERE item_pre_orden_id = $1 AND department_id = $2 LIMIT 1";
        $checkParams = [$itemId, $departmentId];
        $checkResult = pg_query_params($conn, $checkSql, $checkParams);

        if (pg_num_rows($checkResult) > 0) {
            $resultados[] = [
                "index" => $index,
                "success" => false,
                "message" => "Ya existe una planificación para ese item y departamento"
            ];
            $todosExitosos = false;
            continue;
        }

        $sql = "INSERT INTO public.planificacion_work (
                    item_pre_orden_id, department_id, design_image_id, id_producto, comentario_work
                ) VALUES ($1, $2, $3, $4, $5)
                RETURNING planificacion_work_id";

        $params = [$itemId, $departmentId, $designImageId, $productoId, $comentario];
        $insertResult = pg_query_params($conn, $sql, $params);

        if (!$insertResult) {
            $resultados[] = [
                "index" => $index,
                "success" => false,
                "message" => "Error al insertar: " . pg_last_error($conn)
            ];
            $todosExitosos = false;
            continue;
        }

        $inserted = pg_fetch_assoc($insertResult);
        $resultados[] = [
            "index" => $index,
            "success" => true,
            "message" => "Planificación creada exitosamente",
            "planificacion" => $inserted
        ];
    }

    // Si todo fue exitoso, aplicar el UPDATE
    if ($todosExitosos && $itemPreOrdenIdGlobal !== null) {
        $updateSql = "UPDATE public.item_pre_orden SET is_produccion = true WHERE item_pre_orden_id = $1";
        $updateResult = pg_query_params($conn, $updateSql, [$itemPreOrdenIdGlobal]);

        if (!$updateResult) {
            json_response([
                "success" => false,
                "message" => "Error al actualizar is_produccion: " . pg_last_error($conn),
                "resultados" => $resultados
            ], 500);
        }
    }

    json_response([
        "success" => true,
        "message" => $todosExitosos
            ? "Todas las planificaciones fueron creadas y se actualizó is_produccion"
            : "Algunas planificaciones fallaron, no se actualizó is_produccion",
        "resultados" => $resultados
    ]);
} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage()
    ], 500);
}