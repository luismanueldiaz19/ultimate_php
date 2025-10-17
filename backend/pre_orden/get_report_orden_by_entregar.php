<?php
include '../conexion.php';
include '../utils.php';
header("Content-Type: application/json; charset=UTF-8");

try {
    // Leer el body JSON o fallback a $_POST
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // Validar conexión
    if (!$conn) {
        throw new Exception("Error de conexión a la base de datos");
    }

    // Campos esperados
    $fecha_inicio = trim($input['fecha_inicio'] ?? '');
    $fecha_fin = trim($input['fecha_fin'] ?? '');
    $limit = $input['limit'] ?? '';

    // Validar campos obligatorios
    $faltantes = [];
    if (empty($fecha_inicio)) $faltantes[] = 'fecha_inicio';
    if (empty($fecha_fin)) $faltantes[] = 'fecha_fin';
    if (empty($limit)) $faltantes[] = 'limit';

    if ($faltantes) {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios incompletos',
            'faltantes' => $faltantes
        ]);
        exit;
    }

    // Validar formato de fecha (YYYY-MM-DD)
    $regex_fecha = '/^\d{4}-\d{2}-\d{2}$/';
    if (!preg_match($regex_fecha, $fecha_inicio) || !preg_match($regex_fecha, $fecha_fin)) {
        throw new Exception("Formato de fecha inválido. Usa YYYY-MM-DD");
    }

    // Validar que limit sea número entero positivo
    if (!filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        throw new Exception("El campo 'limit' debe ser un número entero positivo");
    }

    // Consulta segura con parámetros
    $query = "
        SELECT 
            pre_orden_id, num_orden, id_cliente, creado_en,
            fecha_entrega, total_bruto, total_itbis,estado_general,
            total_final, name_logo
        FROM public.pre_orden
        WHERE estado_general IS DISTINCT FROM 'ENTREGADO'
          AND DATE(fecha_entrega) BETWEEN $1 AND $2
        ORDER BY fecha_entrega ASC
        LIMIT $3
    ";

    $result = pg_query_params($conn, $query, [$fecha_inicio, $fecha_fin, $limit]);

    if (!$result) {
        throw new Exception("Error al ejecutar la consulta: " . pg_last_error($conn));
    }

    // Construir arreglo de resultados
    $data = [];
    while ($row = pg_fetch_assoc($result)) {
        $data[] = array_map('utf8_encode', $row); // opcional, si tienes tildes
    }

    // Respuesta final
    echo json_encode([
        "success" => true,
        "fecha_inicio" => $fecha_inicio,
        "fecha_fin" => $fecha_fin,
        "total_entregas" => count($data),
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} finally {
    if ($conn) pg_close($conn);
}
?>
