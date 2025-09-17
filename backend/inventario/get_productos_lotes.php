<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $query = "
        SELECT
            pl.productos_lote_id,
            pl.id_producto,
            p.nombre_producto,
            p.codigo_producto,
            pl.id_almacen,
            a.nombre_almacen,
            pl.fecha_vencimiento,
            pl.cantidad,
            pl.reservado,
            pl.disponible,
            pl.creado_en,
            pl.actualizado_en
        FROM public.productos_lotes pl
        LEFT JOIN public.productos p ON pl.id_producto = p.id_producto
        LEFT JOIN public.almacenes a ON pl.id_almacen = a.id_almacen
        ORDER BY pl.id_producto, pl.productos_lote_id
    ";

    $result = pg_query($conn, $query);

    if (!$result) {
        throw new Exception("Error en la consulta");
    }

    $data = [];
    while ($row = pg_fetch_assoc($result)) {
        $data[] = $row;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Inventario cargado correctamente',
        'data' => $data
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} finally {
    pg_close($conn);
}
?>