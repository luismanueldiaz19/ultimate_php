<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    // Capturar y decodificar JSON
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Validar campos obligatorios
    $requiredFields = ['date1', 'date2'];
    $missingFields = array_filter($requiredFields, fn($f) => empty(trim($data[$f] ?? '')));

    if ($missingFields) {
        json_response([
            "success" => false,
            "message" => "Faltan campos obligatorios",
            "faltantes" => $missingFields
        ], 400);
    }

    // Preparar fechas con rango horario completo
    $dateStart = $data['date1'] . ' 00:00:00';
    $dateEnd   = $data['date2'] . ' 23:59:59';

    // Paginación
    $page  = max(1, intval($data['page'] ?? 1));
    $limit = max(1, intval($data['limit'] ?? 20));
    $offset = ($page - 1) * $limit;

    // Consulta con JOINs y filtros
    $query = "
        SELECT
            mi.movimientos_inventario_id,
            mi.tipo_movimiento_id,
            tm.tipo_movimiento,
            mi.cantidad,
            mi.motivo,
            mi.origen_almacen_id,
            a.nombre_almacen AS origen_almacen,
            mi.usuario,
            mi.creado_en,
            mi.actualizado_en,
            mi.productos_lote_id,
            p.codigo_producto,
            p.nombre_producto,
            pl.fecha_vencimiento,
            pl.disponible
        FROM public.movimientos_inventario mi
        LEFT JOIN public.tipo_movimiento tm ON mi.tipo_movimiento_id = tm.tipo_movimiento_id
        LEFT JOIN public.productos_lotes pl ON mi.productos_lote_id = pl.productos_lote_id
        LEFT JOIN public.productos p ON pl.id_producto = p.id_producto
        LEFT JOIN public.almacenes a ON mi.origen_almacen_id = a.id_almacen
        WHERE mi.creado_en BETWEEN $1 AND $2
        ORDER BY mi.creado_en DESC
        LIMIT $3 OFFSET $4
    ";

    $params = [$dateStart, $dateEnd, $limit, $offset];
    $result = pg_query_params($conn, $query, $params);

    if (!$result) {
        throw new Exception("Error en la consulta");
    }

    $rows = [];
    while ($row = pg_fetch_assoc($result)) {
        $rows[] = $row;
    }

    json_response([
        'success' => true,
        'message' => 'Movimientos cargados correctamente',
        'page' => $page,
        'limit' => $limit,
        'data' => $rows
    ]);
} catch (Exception $e) {
    json_response([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], 500);
} finally {
    pg_close($conn);
}
?>