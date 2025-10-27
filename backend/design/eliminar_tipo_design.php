<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    // Leer el body JSON
    $input = json_decode(file_get_contents('php://input'), true);

    // Escapar y validar datos
    $fields = [
        'id' => $input['id'] ?? '',
    ];

    foreach ($fields as $key => $value) {
        $fields[$key] = pg_escape_string($conn, $value);
    }

    if (empty($fields['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'El campo id es obligatorio.'
        ]);
        exit;
    }

    // Iniciar transacción
    pg_query($conn, 'BEGIN');

    try {
        // 1️⃣ Verificar si el registro existe en design_tipo
        $exist_query = "SELECT COUNT(*) FROM public.design_tipo WHERE design_tipo_id = $1";
        $exist_result = pg_query_params($conn, $exist_query, [$fields['id']]);
        $exist_count = pg_fetch_result($exist_result, 0, 0);

        if ($exist_count == 0) {
            pg_query($conn, 'ROLLBACK');
            echo json_encode([
                'success' => false,
                'message' => 'El registro no existe en design_tipo.'
            ]);
            exit;
        }

        // 2️⃣ Verificar si existen registros asociados en design_images_items
        $check_query = "SELECT COUNT(*) FROM public.design_images_items WHERE design_tipo_id = $1";
        $check_result = pg_query_params($conn, $check_query, [$fields['id']]);
        $count = pg_fetch_result($check_result, 0, 0);

        if ($count > 0) {
            pg_query($conn, 'ROLLBACK');
            echo json_encode([
                'success' => false,
                'message' => 'No se puede eliminar. Existen registros relacionados en design_images_items.'
            ]);
            exit;
        }

        // 3️⃣ Eliminar si no hay registros relacionados
        $delete_query = "DELETE FROM public.design_tipo WHERE design_tipo_id = $1";
        $delete_result = pg_query_params($conn, $delete_query, [$fields['id']]);

        if (!$delete_result) {
            throw new Exception(pg_last_error($conn));
        }

        // Confirmar transacción
        pg_query($conn, 'COMMIT');

        echo json_encode([
            'success' => true,
            'message' => 'Registro eliminado exitosamente.'
        ]);

    } catch (Exception $e) {
        pg_query($conn, 'ROLLBACK');
        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar: ' . $e->getMessage()
        ]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
