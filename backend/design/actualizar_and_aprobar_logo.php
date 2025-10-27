<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    // Leer el body JSON
    $input = json_decode(file_get_contents('php://input'), true);

    // Validar y limpiar datos
    $fields = [
        'design_tipo_id'  => $input['design_tipo_id'] ?? '',
        'nombre_estado'   => $input['nombre_estado'] ?? ''
    ];

    foreach ($fields as $key => $value) {
        $fields[$key] = trim(pg_escape_string($conn, $value));
    }

    // Validar campos obligatorios
    if (empty($fields['design_tipo_id']) || empty($fields['nombre_estado'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Faltan campos obligatorios: design_tipo_id o nombre_estado.'
        ]);
        exit;
    }

    // Iniciar transacción
    pg_query($conn, 'BEGIN');

    try {
        // Verificar si el estado existe
        $estado_query = "SELECT id FROM public.estado_aprobacion WHERE nombre_estado = $1";
        $estado_result = pg_query_params($conn, $estado_query, [$fields['nombre_estado']]);
        $estado_row = pg_fetch_assoc($estado_result);

        if (!$estado_row) {
            pg_query($conn, 'ROLLBACK');
            echo json_encode([
                'success' => false,
                'message' => 'El estado especificado no existe en la tabla estado_aprobacion.'
            ]);
            exit;
        }

        $estado_id = $estado_row['id'];

        // Actualizar estado_aprobacion_id en design_tipo
        $update_query = "
            UPDATE public.design_tipo
            SET estado_aprobacion_id = $1
            WHERE design_tipo_id = $2
        ";
        $update_result = pg_query_params($conn, $update_query, [$estado_id, $fields['design_tipo_id']]);

        if (!$update_result) {
            throw new Exception(pg_last_error($conn));
        }

        // Confirmar transacción
        pg_query($conn, 'COMMIT');

        echo json_encode([
            'success' => true,
            'message' => 'El estado del diseño ha sido actualizado correctamente.',
            'new_estado' => $fields['nombre_estado']
        ]);

    } catch (Exception $e) {
        // En caso de error, revertir cambios
        pg_query($conn, 'ROLLBACK');
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar: ' . $e->getMessage()
        ]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Solo se acepta POST.'
    ]);
}
?>
