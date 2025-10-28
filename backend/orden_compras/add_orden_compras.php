<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    $input = json_decode(file_get_contents('php://input'), true);

    // Validar campos obligatorios
    $required = ['num_factura', 'id_proveedor', 'fecha', 'productos'];
    $empty_fields = [];
    foreach ($required as $field) {
        if (empty($input[$field])) {
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

    // Iniciar transacción
    pg_query($conn, "BEGIN");

    // Insertar encabezado
    $insert_encabezado = "
        INSERT INTO orden_compra (
            num_factura, id_proveedor, fecha, observaciones
        ) VALUES (
            $1, $2, $3, $4
        ) RETURNING orden_compra_id
    ";

    $params_encabezado = [
        $input['num_factura'],
        $input['id_proveedor'],
        $input['fecha'],
        $input['observaciones'] ?? ''
    ];

    $res_encabezado = @pg_query_params($conn, $insert_encabezado, $params_encabezado);

    if (!$res_encabezado) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(['success' => false, 'message' => 'Error al registrar encabezado: ' . pg_last_error($conn)]);
        exit;
    }

    $orden_id = pg_fetch_result($res_encabezado, 0, 'orden_compra_id');

    // Insertar productos
    $errores = [];
    foreach ($input['productos'] as $producto) {
        $insert_detalle = "
            INSERT INTO orden_compra_detalle (
                orden_compra_id, id_producto, cantidad, costo_unitario
            ) VALUES (
                $1, $2, $3, $4
            )
        ";

        $params_detalle = [
            $orden_id,
            $producto['id_producto'],
            $producto['cantidad'],
            $producto['costo_unitario']
        ];

        $res_detalle = pg_query_params($conn, $insert_detalle, $params_detalle);

        if (!$res_detalle) {
            $errores[] = pg_last_error($conn);
        }
    }

    if (!empty($errores)) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(['success' => false, 'message' => 'Error en productos', 'errors' => $errores]);
    } else {
        pg_query($conn, "COMMIT");
        echo json_encode(['success' => true, 'message' => 'Orden de compra registrada exitosamente', 'orden_id' => $orden_id]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, "message" => "Método no permitido"]);
}
?>