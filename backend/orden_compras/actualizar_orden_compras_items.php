<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

     $input = json_decode(file_get_contents('php://input'), true);

    $required = ['orden_compra_id', 'id_producto', 'nueva_cantidad', 'nuevo_costo_unitario'];
    $empty_fields = [];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            $empty_fields[] = $field;
        }
    }

    if (!empty($empty_fields)) {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios faltantes',
            'empty_fields' => $empty_fields
        ]);
        exit;
    }

    $orden_id = $input['orden_compra_id'];
    $producto_id = $input['id_producto'];
    $nueva_cantidad = $input['nueva_cantidad'];
    $nuevo_costo = $input['nuevo_costo_unitario'];

    // Validar estado
    $estado_query = "SELECT estado FROM orden_compra WHERE orden_compra_id = $1";
    $res_estado = pg_query_params($conn, $estado_query, [$orden_id]);

    if (!$res_estado || pg_num_rows($res_estado) === 0) {
        echo json_encode(['success' => false, 'message' => 'Orden no encontrada']);
        exit;
    }

    $estado = pg_fetch_result($res_estado, 0, 'estado');
    if ($estado === 'aprobada' || $estado === 'cerrada') {
        echo json_encode(['success' => false, 'message' => 'No se puede modificar una orden ya aprobada o cerrada']);
        exit;
    }

    // Obtener valores anteriores
    $detalle_query = "
        SELECT cantidad, costo_unitario
        FROM orden_compra_detalle
        WHERE orden_compra_id = $1 AND id_producto = $2
    ";
    $res_detalle = pg_query_params($conn, $detalle_query, [$orden_id, $producto_id]);

    if (!$res_detalle || pg_num_rows($res_detalle) === 0) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado en la orden']);
        exit;
    }

    $row = pg_fetch_assoc($res_detalle);
    $cantidad_anterior = $row['cantidad'];
    $costo_anterior = $row['costo_unitario'];

    // Generar nota con valores anteriores y nuevos
    $nota = "Modificado: cantidad $cantidad_anterior → $nueva_cantidad, costo $costo_anterior → $nuevo_costo";

    // Actualizar cantidad, costo y nota
    $update_query = "
        UPDATE orden_compra_detalle
        SET cantidad = $1,
            costo_unitario = $2,
            nota_producto = $3
        WHERE orden_compra_id = $4 AND id_producto = $5
    ";

    $params = [$nueva_cantidad, $nuevo_costo, $nota, $orden_id, $producto_id];
    $res_update = pg_query_params($conn, $update_query, $params);

    if ($res_update) {
        echo json_encode(['success' => true, 'message' => 'Producto actualizado con nota registrada']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . pg_last_error($conn)]);
    }

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, "message" => "Método no permitido"]);
}
?>
