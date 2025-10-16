<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
pg_set_client_encoding($conn, "UTF8");
try {
    $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
    $date1 = trim($data['date1'] ?? '');
    $date2 = trim($data['date2'] ?? '');

    if (!$conn) {
        json_response(["success" => false, "message" => "Sin conexión a la base de datos."], 500);
    }

    if (empty($date1) || empty($date2)) {
        json_response(["success" => false, "message" => "Las fechas 'date1' y 'date2' son obligatorias."], 400);
    }

    $sql = "
        SELECT 
            cc.tipo,
            cc.codigo,
            cc.nombre,
            SUM(da.debe - da.haber) AS resultado
        FROM public.detalle_asiento da
        INNER JOIN public.asientos_contables ac ON da.id_asiento = ac.id_asiento
        INNER JOIN public.catalogo_cuentas cc ON da.codigo_cuenta = cc.codigo
        WHERE cc.tipo IN ('ingreso', 'gasto') AND ac.fecha BETWEEN $1 AND $2
        GROUP BY cc.tipo, cc.codigo, cc.nombre
        ORDER BY cc.tipo, cc.codigo
    ";

    $result = pg_query_params($conn, $sql, [$date1, $date2]);
    if (!$result) {
        json_response(["success" => false, "message" => pg_last_error($conn)], 500);
    }

    $rows = pg_fetch_all($result) ?? [];
    $estado_resultados = array_map(fn($row) => array_map('utf8_encode', $row), $rows);

    json_response([
        "success" => true,
        "estado_resultados" => $estado_resultados,
        "total_registros" => count($estado_resultados)
    ]);
} catch (Throwable $e) {
    json_response(["success" => false, "message" => "Error inesperado: " . $e->getMessage()], 500);
}