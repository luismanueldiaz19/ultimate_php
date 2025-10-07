<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $productos = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!is_array($productos)) {
        json_response(["success" => false, "message" => "Se esperaba una lista de productos"], 400);
    }

    $requiredFields = ['nombre_producto', 'categoria_id', 'precio_compra', 'precio_venta', 'proveedor_id', 'unidad_medida_id', 'impuesto_id', 'almacen_id', 'cuenta_contable_id', 'id_usuario', 'productos_catalogos_id'];
    $insertados = [];
    $errores = [];

    foreach ($productos as $index => $data) {
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $errores[] = [
                "index" => $index,
                "message" => "Faltan campos obligatorios",
                "faltantes" => $missingFields
            ];
            continue;
        }

        // Valores
        $nombre        = trim($data['nombre_producto']);
        $categoria_id  = (int)$data['categoria_id'];
        $proveedor_id  = $data['proveedor_id'];
        $unidad_id     = $data['unidad_medida_id'];
        $impuesto_id   = $data['impuesto_id'];
        $almacen_id    = $data['almacen_id'];
        $cuenta_id     = $data['cuenta_contable_id'];
        $precio_compra = (float)$data['precio_compra'];
        $precio_venta  = (float)$data['precio_venta'];
        $stock_actual  = (float)($data['stock_actual'] ?? 0);
        $stock_minimo  = (float)($data['stock_minimo'] ?? 0);
        $activo        = isset($data['activo']) ? ($data['activo'] ? 't' : 'f') : 't';
        $usuarioId     = $data['id_usuario'];
        $productos_catalogos_id   = $data['productos_catalogos_id'];
        $color = isset($data['color']) && is_array($data['color']) ? json_encode($data['color']) : null;


        // Código automático
        if (empty($data['codigo_producto'])) {
            $res = pg_query_params($conn, "SELECT generar_codigo_producto($1) AS codigo", [$categoria_id]);
            $row = pg_fetch_assoc($res);
            $codigo = $row['codigo'] ?? 'N/A';
        } else {
            $codigo = trim($data['codigo_producto']);
        }

        // Insertar
        $sql = "INSERT INTO productos 
            (nombre_producto, codigo_producto, categoria_id, proveedor_id, unidad_medida_id, impuesto_id, almacen_id, cuenta_contable_id, precio_compra, precio_venta, stock_actual, stock_minimo, activo,productos_catalogos_id,color)
            VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15)
            RETURNING id_producto, nombre_producto, codigo_producto, categoria_id, precio_compra, precio_venta, stock_actual, stock_minimo, activo, color";

        $params = [$nombre,$codigo, $categoria_id, $proveedor_id, $unidad_id, $impuesto_id, $almacen_id, $cuenta_id, $precio_compra, $precio_venta, $stock_actual, $stock_minimo, $activo,$productos_catalogos_id, $color];
        $result = pg_query_params($conn, $sql, $params);

        if (!$result) {
            $errores[] = [
                "index" => $index,
                "message" => "Error al crear producto: " . pg_last_error($conn)
            ];
            continue;
        }

        $newProduct = pg_fetch_assoc($result);
        registrarAuditoria($conn, $usuarioId, 'INSERT', 'productos', null, $newProduct);
        $insertados[] = $newProduct;
    }

    json_response([
        "success" => true,
        "insertados" => $insertados,
        "errores" => $errores
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}