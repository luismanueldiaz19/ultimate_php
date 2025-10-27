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
            "message" => "Se esperaba una lista de planificaciones"
        ], 400);
    }

    $enviadosProduccion = 0;
    $yaExistentes = 0;
    $departamentosNoEncontrados = [];

    foreach ($data as $index => $item) {
        $itemId = intval($item['item_pre_orden_id'] ?? 0);
        $nombreDepartamento = strtolower(trim($item['department'] ?? ''));
        $notaProducto = trim($item['nota_producto'] ?? '');
        $id_producto = trim($item['id_producto'] ?? '');

        if ($itemId === 0 || $nombreDepartamento === '') {
            continue; // Datos incompletos, ignorar
        }

        // Buscar department_id por nombre
        $sqlDept = "SELECT department_id FROM public.departments WHERE LOWER(name_department) = $1 LIMIT 1";

        $resDept = pg_query_params($conn, $sqlDept, [$nombreDepartamento]);

        if (pg_num_rows($resDept) === 0) {
            $departamentosNoEncontrados[] = [
                "id_producto" => $id_producto,
                "item_pre_orden_id" => $itemId,
                "department" => $nombreDepartamento,
                "nota_producto" => $notaProducto
            ];
            $sqlUpdate = "UPDATE public.item_pre_orden  SET estado_item = 'COMPLETADO', is_produccion = true  WHERE item_pre_orden_id = $1";
            pg_query_params($conn, $sqlUpdate, [$itemId]);
            continue;
        }

        // UPDATE public.item_pre_orden SET estado_item = 'COMPLETADO' WHERE item_pre_orden_id = ?

        $departmentId = pg_fetch_result($resDept, 0, 'department_id');

        // Verificar si ya existe planificación
        $sqlCheck = "SELECT 1 FROM public.planificacion_work WHERE item_pre_orden_id = $1 AND department_id = $2 LIMIT 1";

        $resCheck = pg_query_params($conn, $sqlCheck, [$itemId, $departmentId]);

        if (pg_num_rows($resCheck) > 0) {
            $yaExistentes++;
            continue;
        }

        // Insertar planificación
        $sqlInsert = "INSERT INTO public.planificacion_work (id_producto, item_pre_orden_id, department_id, comentario_work)
                      VALUES ($1, $2, $3, $4)";
        $paramsInsert = [$id_producto, $itemId, $departmentId, $notaProducto];
        $resInsert = pg_query_params($conn, $sqlInsert, $paramsInsert);

        if ($resInsert) {
            // Actualizar is_produccion
            $sqlUpdate = "UPDATE public.item_pre_orden SET is_produccion = true WHERE item_pre_orden_id = $1";
            pg_query_params($conn, $sqlUpdate, [$itemId]);
            $enviadosProduccion++;
        }
    }

    // Reporte final
    json_response([
        "success" => true,
        "message" => "Proceso completado",
        "resumen" => [
            "enviados_a_produccion" => $enviadosProduccion,
            "ya_existentes" => $yaExistentes,
            "departamentos_no_encontrados" => count($departamentosNoEncontrados),
            "detalle_departamentos_fallidos" => $departamentosNoEncontrados
        ]
    ]);
} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage()
    ], 500);
}