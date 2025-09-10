<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    // Obtener filtro desde GET
    $nombreFiltro = $_GET['nombre'] ?? null;

    // Consulta con filtro opcional
    $sql = "SELECT 
                m.id,
                m.materiales_gastable_id,
                mg.nombre_materia,
                mg.unidad,
                mg.ubicacion,
                m.compra_id,
                m.tipo,
                m.cantidad,
                m.fecha,
                m.referencia
            FROM public.movimientos m
            INNER JOIN public.materiales_gastable mg ON m.materiales_gastable_id = mg.materiales_gastable_id
            WHERE ($1::TEXT IS NULL OR mg.nombre_materia ILIKE '%' || $1::TEXT || '%')
            ORDER BY m.fecha DESC
            LIMIT 500";

    $result = pg_query_params($conn, $sql, [$nombreFiltro]);

    if (!$result) {
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al consultar movimientos: " . $error], 500);
    }

    $movimientos = [];
    while ($row = pg_fetch_assoc($result)) {
        $movimientos[] = $row;
    }

    json_response([
        "success" => true,
        "total" => count($movimientos),
        "movimientos" => $movimientos
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>