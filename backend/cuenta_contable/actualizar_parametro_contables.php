<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    // Leer el body JSON
    $input = json_decode(file_get_contents('php://input'), true);

    // Campos de la tabla parametros_contables
    $fields = [
        'parametros_contables_id' => $input['parametros_contables_id'] ?? '',
        'nombre_operacion'        => $input['nombre_operacion'] ?? '',
        'codigo_cuenta'           => $input['codigo_cuenta'] ?? '',
        'descripcion'             => $input['descripcion'] ?? '',
        'tipo_movimiento'         => $input['tipo_movimiento'] ?? ''
    ];

    // Escapar cada campo
    foreach ($fields as $key => $value) {
        $fields[$key] = pg_escape_string($conn, $value);
    }

    // Validar campos obligatorios (parametros_contables_id)
    if (empty($fields['parametros_contables_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'El ID del parámetro contable es obligatorio'
        ]);
        exit;
    }

    // Query de actualización
    $update_query = "UPDATE public.parametros_contables 
                     SET nombre_operacion = $1,
                         codigo_cuenta    = $2,
                         descripcion      = $3,
                         tipo_movimiento  = $4
                     WHERE parametros_contables_id = $5";

    $params = [
        $fields['nombre_operacion'],
        $fields['codigo_cuenta'],
        $fields['descripcion'],
        $fields['tipo_movimiento'],
        $fields['parametros_contables_id']
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
