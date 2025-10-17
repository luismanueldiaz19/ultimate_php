<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
include '../utils.php';


    // Leer el body JSON
    $input = json_decode(file_get_contents('php://input'), true);

    // Escapar y mapear campos
    $fields = [
        'fecha' => $input['fecha'] ?? '',
        'id_proveedor' => $input['id_proveedor'] ?? '',
        'tipo_gasto_id' => $input['tipo_gasto_id'] ?? '',
        'descripcion' => $input['descripcion'] ?? '',
        'monto_total' => $input['monto_total'] ?? '',
        'itbis' => $input['itbis'] ?? '0.00',
        'forma_pago' => $input['forma_pago'] ?? '',
        'cuenta_gasto_codigo' => $input['cuenta_gasto_codigo'] ?? '',
        'cuenta_pago_codigo' => $input['cuenta_pago_codigo'] ?? '',
        'centro_costo' => $input['centro_costo'] ?? '',
        'estado' => $input['estado'] ?? 'registrado'
    ];

    // Validar campos obligatorios
    $required = ['fecha', 'id_proveedor', 'tipo_gasto_id', 'monto_total', 'cuenta_gasto_codigo','cuenta_pago_codigo','descripcion'];
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

    // Query parametrizada
    $insert_query = "
        INSERT INTO public.gasto_empresa (
            fecha, id_proveedor, tipo_gasto_id, descripcion,
            monto_total, itbis, forma_pago,
            cuenta_gasto_codigo, cuenta_pago_codigo,
            centro_costo, estado
        ) VALUES (
            $1, $2, $3, $4,
            $5, $6, $7,
            $8, $9,
            $10, $11
        )
    ";

    $params = [
        $fields['fecha'],
        $fields['id_proveedor'],
        $fields['tipo_gasto_id'],
        $fields['descripcion'],
        $fields['monto_total'],
        $fields['itbis'],
        $fields['forma_pago'],
        $fields['cuenta_gasto_codigo'],
        $fields['cuenta_pago_codigo'],
        $fields['centro_costo'],
        $fields['estado']
    ];

    $result = pg_query_params($conn, $insert_query, $params);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Gasto registrado exitosamente.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al registrar: ' . pg_last_error($conn)
        ]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, "message" => "Método no permitido"]);
}
?>