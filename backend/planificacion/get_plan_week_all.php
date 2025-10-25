<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

    $date1 = isset($data['date1']) ? trim($data['date1']) : '';
    $date2 = isset($data['date2']) ? trim($data['date2']) : '';

    if (!strtotime($date1) || !strtotime($date2)) {
        json_response([
            "success" => false,
            "message" => "Fechas inválidas"
        ], 400);
    }

 
    $sql = "
        SELECT 
            planificador.planificador_id,
            planificador.item_pre_orden_id,
            planificador.planificacion_work_id,
            departments.name_department,
            departments.department_id,
            planificador.index_panificacion,
            planificador.estado_planificador,
            planificador.fecha_planificacion,
            planificador.creado_en,
            planificador.comentario_planificador,
            pre_orden.num_orden,
            item_pre_orden.cant,
            item_pre_orden.nota_producto,
            productos.id_producto,
            productos.codigo_producto,
            productos.linea,
            productos.material,
            productos.estilo,
            productos.marca,
            productos.genero,
            productos.color,
            productos.size,
            design_tipo.tipo_trabajo
        FROM public.planificador
        INNER JOIN public.planificacion_work AS m ON m.planificacion_work_id = planificador.planificacion_work_id
        INNER JOIN public.departments ON departments.department_id = m.department_id
        INNER JOIN public.item_pre_orden ON item_pre_orden.item_pre_orden_id = m.item_pre_orden_id
        INNER JOIN public.pre_orden ON pre_orden.pre_orden_id = item_pre_orden.pre_orden_id
        INNER JOIN public.productos ON productos.id_producto = m.id_producto
        INNER JOIN public.design_tipo ON design_tipo.design_tipo_id = item_pre_orden.design_tipo_id
        WHERE planificador.fecha_planificacion BETWEEN $1 AND $2
        ORDER BY planificador.index_panificacion :: integer, departments.name_department ASC Limit 500;
    ";

    $params = [$date1, $date2];
    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        throw new Exception("Error en la consulta: " . pg_last_error($conn));
    }

    $rows = pg_fetch_all($result) ?? [];
    $planificaciones = [];

    foreach ($rows as $row) {
        $dataProducto = [
            'id_producto' => $row['id_producto'],
            'codigo_producto' => $row['codigo_producto'],
            'linea' => $row['linea'],
            'material' => $row['material'],
            'estilo' => $row['estilo'],
            'marca' => $row['marca'],
            'genero' => $row['genero'],
            'color' => $row['color'],
            'size' => $row['size'],
        ];

        $planificaciones[] = [
            "planificador_id" => $row['planificador_id'],
            "item_pre_orden_id" => $row['item_pre_orden_id'],
            "planificacion_work_id" => $row['planificacion_work_id'],
            "name_department" => $row['name_department'],
            "department_id" => $row['department_id'],
            "index_panificacion" => $row['index_panificacion'],
            "estado_planificador" => $row['estado_planificador'],
            "fecha_planificacion" => $row['fecha_planificacion'],
            "creado_en" => $row['creado_en'],
            "comentario_planificador" => $row['comentario_planificador'],
            "num_orden" => $row['num_orden'],
            "cant" => $row['cant'],
            "nota_producto" => $row['nota_producto'],
            "tipo_trabajo" => $row['tipo_trabajo'],
            "producto" => $dataProducto
        ];
    }

    json_response([
        "success" => true,
        "plan" => $planificaciones,
        "total" => count($planificaciones)
    ]);
} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage()
    ], 500);
}