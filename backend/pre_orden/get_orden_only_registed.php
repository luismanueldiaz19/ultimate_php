<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';
    header("Content-Type: application/json");

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $fields = [
        'date1' => $input['date1'] ?? '',
        'date2' => $input['date2'] ?? '',
    ];

    foreach ($fields as $key => $value) {
        $fields[$key] = pg_escape_string($conn, $value);
    }

    $required = ['date1', 'date2'];
    $empty_fields = [];

    foreach ($required as $field) {
        if (empty($fields[$field])) {
            $empty_fields[] = $field;
        }
    }

    if (!empty($empty_fields)) {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios incompletos',
            'empty_fields' => $empty_fields
        ]);
        exit;
    }

    $sql = "SELECT 
        UPPER(usuarios.nombre) AS usuario_nombre,
        COUNT(DISTINCT p.num_orden) AS total_ordenes,
        SUM(item_pre_orden.cant) AS total_piezas,
        SUM(p.total_final) AS total_monto
    FROM public.pre_orden p
    INNER JOIN public.item_pre_orden ON item_pre_orden.pre_orden_id = p.pre_orden_id
    INNER JOIN public.usuarios ON usuarios.id_usuario = p.id_usuario
    WHERE DATE(p.creado_en) BETWEEN $1 AND $2
    GROUP BY usuarios.nombre
    ORDER BY usuarios.nombre ASC";

    $params = [$fields['date1'], $fields['date2']];

    try {
        $res = pg_query_params($conn, $sql, $params);
        $resumen = pg_fetch_all($res);

        echo json_encode([
            'success' => true,
            'data' => $resumen ?: []
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }

}
?>