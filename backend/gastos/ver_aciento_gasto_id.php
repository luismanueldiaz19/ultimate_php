<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header("Content-Type: application/json");
    include '../conexion.php';
    include '../utils.php';

    // Leer JSON enviado por POST
    $input = json_decode(file_get_contents('php://input'), true);
    $asiento_id = $input['asiento_id'] ?? null;

    if (!$asiento_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Debes indicar el ID del asiento'
        ]);
        exit;
    }

    // Consulta del asiento principal
    $query_asiento = "SELECT id_asiento, fecha, descripcion, total, estado, id_cliente
                      FROM public.asientos_contables
                      WHERE id_asiento = $1";
    $res_asiento = pg_query_params($conn, $query_asiento, [$asiento_id]);

    if (!$res_asiento || pg_num_rows($res_asiento) == 0) {
        echo json_encode([
            'success' => false,
            'message' => "No se encontró el asiento con ID $asiento_id"
        ]);
        exit;
    }

    $asiento = pg_fetch_assoc($res_asiento);

    // Consulta del detalle del asiento
    $query_detalle = "
        SELECT da.id_detalle, da.codigo_cuenta, da.debe, da.haber, cc.nombre AS nombre_cuenta
        FROM public.detalle_asiento da
        LEFT JOIN public.catalogo_cuentas cc ON da.codigo_cuenta = cc.codigo
        WHERE da.id_asiento = $1
        ORDER BY da.id_detalle
    ";
    $res_detalle = pg_query_params($conn, $query_detalle, [$asiento_id]);

    $lineas = [];
    while ($row = pg_fetch_assoc($res_detalle)) {
        $lineas[] = $row;
    }

    echo json_encode([
        'success' => true,
        'asiento' => $asiento,
        'detalle' => $lineas
    ]);

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}
?>
