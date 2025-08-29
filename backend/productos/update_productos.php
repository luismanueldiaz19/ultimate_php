<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    $usuarioId = $data['usuario_id'] ?? null;
    if (!$usuarioId) {
        json_response(["success" => false, "message" => "El usuario_id es obligatorio"], 400);
    }

    if (!isset($data['id_producto'])) {
        json_response(["success" => false, "message" => "El id_producto es obligatorio"], 400);
    }

    $id_producto = (int)$data['id_producto'];
    $nombre = trim($data['nombre_producto'] ?? '');
    $descripcion = $data['descripcion'] ?? null;
    $codigo = $data['codigo_producto'] ?? null;
    $categoria_id = $data['categoria_id'] ?? null;
    $proveedor_id = $data['proveedor_id'] ?? null;
    $unidad_medida_id = $data['unidad_medida_id'] ?? null;
    $impuesto_id = $data['impuesto_id'] ?? null;
    $almacen_id = $data['almacen_id'] ?? null;
    $cuenta_contable_id = $data['cuenta_contable_id'] ?? null;
    $precio_compra = $data['precio_compra'] ?? null;
    $precio_venta = $data['precio_venta'] ?? null;
    $stock_actual = $data['stock_actual'] ?? null;
    $stock_minimo = $data['stock_minimo'] ?? null;
    $activo = $data['activo'] ?? null;

    if ($activo !== null) {
        $activo = $activo ? 't' : 'f';
    }

    // Verificar duplicado de código si se proporciona
    if ($codigo) {
        $check = pg_query_params($conn, "SELECT 1 FROM productos WHERE codigo_producto = $1 AND id_producto != $2 LIMIT 1", [$codigo, $id_producto]);
        if (pg_num_rows($check) > 0) {
            json_response(["success" => false, "message" => "El código de producto ya existe"], 409);
        }
    }

    // Construir actualización dinámica
    $fields = [];
    $params = [];
    $i = 1;

    $map = [
        'nombre_producto' => $nombre,
        'descripcion' => $descripcion,
        'codigo_producto' => $codigo,
        'categoria_id' => $categoria_id,
        'proveedor_id' => $proveedor_id,
        'unidad_medida_id' => $unidad_medida_id,
        'impuesto_id' => $impuesto_id,
        'almacen_id' => $almacen_id,
        'cuenta_contable_id' => $cuenta_contable_id,
        'precio_compra' => $precio_compra,
        'precio_venta' => $precio_venta,
        'stock_actual' => $stock_actual,
        'stock_minimo' => $stock_minimo,
        'activo' => $activo
    ];

    foreach ($map as $key => $val) {
        if ($val !== null) {
            $fields[] = "$key = $$i";
            $params[] = $val;
            $i++;
        }
    }

    if (empty($fields)) {
        json_response(["success" => false, "message" => "No hay campos para actualizar"], 400);
    }

    // Obtener datos anteriores para auditoría
    $res = pg_query_params($conn, "SELECT * FROM productos WHERE id_producto = $1", [$id_producto]);
    $datos_anteriores = pg_fetch_assoc($res);

    // Agregar parámetro del id_producto
    $params[] = $id_producto;

    $sql = "UPDATE productos SET " . implode(', ', $fields) . ", actualizado_en = NOW() WHERE id_producto = $$i RETURNING *";

    $result = pg_query_params($conn, $sql, $params);
    if (!$result) {
        json_response([
            "success" => false,
            "message" => "Error al actualizar producto: " . pg_last_error($conn)
        ], 500);
    }

    $updatedProducto = pg_fetch_assoc($result);

    // Registrar auditoría
    registrarAuditoria($conn, $usuarioId, 'UPDATE', 'productos', $datos_anteriores, $data);

    json_response([
        "success" => true,
        "message" => "Producto actualizado exitosamente",
        "producto" => $updatedProducto
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage()
    ], 500);
}
?>
