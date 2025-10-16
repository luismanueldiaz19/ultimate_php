<?php
include '../conexion.php';
include '../utils.php';

// Cabeceras HTTP
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
 pg_set_client_encoding($conn, "UTF8");
try {
       $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
    // Verificar conexión
    if (!$conn) {
        json_response([
            "success" => false,
            "message" => "No hay conexión a la base de datos."
        ], 500);
    }
    
    // Leer y validar parámetros
    $date1 = trim($data['date1'] ?? '');
    $date2 = trim($data['date2'] ?? '');

    if (empty($date1) || empty($date2)) {
        json_response([
            "success" => false,
            "message" => "Las fechas 'date1' y 'date2' son obligatorias."
        ], 400);
    }


    // Consulta con parámetros para evitar inyección SQL
    $sql = "
        SELECT 
            ac.id_asiento,
            ac.fecha,
            ac.descripcion,
            da.codigo_cuenta,
            cc.nombre AS nombre_cuenta,
            da.debe,
            da.haber
        FROM public.detalle_asiento da
        INNER JOIN public.asientos_contables ac ON da.id_asiento = ac.id_asiento
        INNER JOIN public.catalogo_cuentas cc ON da.codigo_cuenta = cc.codigo
        WHERE ac.fecha BETWEEN $1 AND $2
        ORDER BY ac.fecha, ac.id_asiento ASC
    ";

    $result = pg_query_params($conn, $sql, [$date1, $date2]);

    if (!$result) {
        json_response([
            "success" => false,
            "message" => "Error al ejecutar la consulta.",
            "error" => pg_last_error($conn)
        ], 500);
    }

    $rows = pg_fetch_all($result);

    // Si no hay resultados
    if (!$rows) {
        json_response([
            "success" => true,
            "message" => "No hay registros en el libro diario para el rango seleccionado.",
            "libro" => [],
            "total_registros" => 0
        ]);
    }

    // Codificar a UTF-8 si fuera necesario
    $libro_diario = array_map(function($row) {
        return array_map('utf8_encode', $row);
    }, $rows);

    // Respuesta exitosa
    json_response([
        "success" => true,
        "total_registros" => count($libro_diario),
        "libro" => $libro_diario
    ]);

} catch (Throwable $e) {
    json_response([
        "success" => false,
        "message" => "Error inesperado: " . $e->getMessage()
    ], 500);
}
?>
