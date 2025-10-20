<?php
include '../conexion.php'; // Asegúrate de que este archivo tenga la conexión $conn
header("Content-Type: application/json");

try {
    $sql = "SELECT tipo_cuenta_id, descricion, status_tipo FROM public.tipo_cuenta ORDER BY tipo_cuenta_id";
    $res = pg_query($conn, $sql);

    if (!$res) {
        throw new Exception(pg_last_error($conn));
    }

    $datos = [];
    while ($row = pg_fetch_assoc($res)) {
        $datos[] = [
            'tipo_cuenta_id' => $row['tipo_cuenta_id'],
            'descricion' => $row['descricion'],
            'status_tipo' => $row['status_tipo']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $datos
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar tipo de cuenta',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>