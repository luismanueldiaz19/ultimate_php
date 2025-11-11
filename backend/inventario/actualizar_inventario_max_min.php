<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    // Leer el body JSON
    $input = json_decode(file_get_contents('php://input'), true);

    // Campos esperados
    $fields = [
        'id_inventario' => $input['id_inventario'] ?? '',
        'stock_minimo'  => $input['stock_minimo'] ?? '',
        'stock_maximo'  => $input['stock_maximo'] ?? ''
    ];

    // Escapar cada campo
    foreach ($fields as $key => $value) {
        $fields[$key] = pg_escape_string($conn, $value);
    }

    // Validar campos obligatorios
    $required = ['id_inventario'];
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

    // Query de actualización
    $update_query = "UPDATE public.inventario SET stock_minimo = $1, stock_maximo = $2 , actualizado_en = NOW() WHERE id_inventario = $3 ";

    $params = [
        $fields['stock_minimo'],
        $fields['stock_maximo'],
        $fields['id_inventario']
    ];

    $result = pg_query_params($conn, $update_query, $params);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Registro actualizado exitosamente.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar: ' . pg_last_error($conn)
        ]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, "message" => "Método no permitido"]);
}
?>