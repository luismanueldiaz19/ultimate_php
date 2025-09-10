<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    // Obtener filtro desde GET
    $nombreFiltro = $_GET['nombre'] ?? null;

    // Consulta con filtro opcional
   $sql = "SELECT materiales_gastable_id, id_cuenta, nombre_materia, unidad, costo, stock_actual, ubicacion
        FROM public.materiales_gastable
        WHERE (CAST($1 AS TEXT) IS NULL OR nombre_materia ILIKE '%' || CAST($1 AS TEXT) || '%')
        LIMIT 500";

    $result = pg_query_params($conn, $sql, [$nombreFiltro]);

    if (!$result) {
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al consultar materiales: " . $error], 500);
    }

    $materiales = [];
    while ($row = pg_fetch_assoc($result)) {
        $materiales[] = $row;
    }

    json_response([
        "success" => true,
        "total" => count($materiales),
        "materiales" => $materiales
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>