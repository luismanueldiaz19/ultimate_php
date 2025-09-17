<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    $date1 = $data['date1'] ?? null;
    $date2 = $data['date2'] ?? null;

    if (!$date1 || !$date2) {
        json_response([
            "success" => false,
            "message" => "Faltan fechas para el análisis"
        ], 400);
    }

    $dateStart = $date1 . ' 00:00:00';
    $dateEnd   = $date2 . ' 23:59:59';

    // 1. Resumen por tipo de movimiento
    $queryTipo = "
        SELECT tm.tipo_movimiento, COUNT(*) AS total_movimientos, SUM(mi.cantidad) AS total_cantidad
        FROM movimientos_inventario mi
        JOIN tipo_movimiento tm ON mi.tipo_movimiento_id = tm.tipo_movimiento_id
        WHERE mi.creado_en BETWEEN $1 AND $2
        GROUP BY tm.tipo_movimiento
        ORDER BY total_movimientos DESC
    ";
    $resTipo = pg_query_params($conn, $queryTipo, [$dateStart, $dateEnd]);
    $rowsTipo = pg_fetch_all($resTipo) ?: [];

    // 2. Rotación por producto
    $queryProducto = "
        SELECT p.nombre_producto, p.codigo_producto, SUM(mi.cantidad) AS total_movido
        FROM movimientos_inventario mi
        JOIN productos_lotes pl ON mi.productos_lote_id = pl.productos_lote_id
        JOIN productos p ON pl.id_producto = p.id_producto
        WHERE mi.creado_en BETWEEN $1 AND $2
        GROUP BY p.nombre_producto, p.codigo_producto
        ORDER BY total_movido DESC
    ";
    $resProducto = pg_query_params($conn, $queryProducto, [$dateStart, $dateEnd]);
    $rowsProducto = pg_fetch_all($resProducto) ?: [];

    // 3. Actividad por almacén
    $queryAlmacen = "
        SELECT a.nombre_almacen, COUNT(*) AS movimientos, SUM(mi.cantidad) AS volumen_total
        FROM movimientos_inventario mi
        JOIN almacenes a ON mi.origen_almacen_id = a.id_almacen
        WHERE mi.creado_en BETWEEN $1 AND $2
        GROUP BY a.nombre_almacen
        ORDER BY volumen_total DESC
    ";
    $resAlmacen = pg_query_params($conn, $queryAlmacen, [$dateStart, $dateEnd]);
    $rowsAlmacen = pg_fetch_all($resAlmacen) ?: [];

    // 4. Movimientos por usuario
    $queryUsuario = "
        SELECT mi.usuario, COUNT(*) AS total_operaciones, SUM(mi.cantidad) AS volumen_total
        FROM movimientos_inventario mi
        WHERE mi.creado_en BETWEEN $1 AND $2
        GROUP BY mi.usuario
        ORDER BY total_operaciones DESC
    ";
    $resUsuario = pg_query_params($conn, $queryUsuario, [$dateStart, $dateEnd]);
    $rowsUsuario = pg_fetch_all($resUsuario) ?: [];

    // 5. Lotes más utilizados
    $queryLotes = "
        SELECT mi.productos_lote_id, p.nombre_producto, COUNT(*) AS veces_usado, SUM(mi.cantidad) AS cantidad_total
        FROM movimientos_inventario mi
        JOIN productos_lotes pl ON mi.productos_lote_id = pl.productos_lote_id
        JOIN productos p ON pl.id_producto = p.id_producto
        WHERE mi.creado_en BETWEEN $1 AND $2
        GROUP BY mi.productos_lote_id, p.nombre_producto
        ORDER BY cantidad_total DESC
    ";
    $resLotes = pg_query_params($conn, $queryLotes, [$dateStart, $dateEnd]);
    
    $rowsLotes = pg_fetch_all($resLotes) ?: [];

    // Respuesta final
    json_response([
        'success' => true,
        'message' => 'Reporte gerencial generado correctamente',
        'resumen_tipo_movimiento' => $rowsTipo,
        'resumen_por_producto' => $rowsProducto,
        'resumen_por_almacen' => $rowsAlmacen,
        'resumen_por_usuario' => $rowsUsuario,
        'resumen_por_lote' => $rowsLotes
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