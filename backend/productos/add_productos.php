<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';  // 👈 Agregado
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Campos obligatorios
    $requiredFields = ['nombre_producto', 'categoria_id', 'precio_compra', 'precio_venta' ,'proveedor_id','unidad_medida_id','impuesto_id', 'almacen_id','cuenta_contable_id', 'id_usuario','color'];
    
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        json_response([
            "success" => false,
            "message" => "Faltan campos obligatorios",
            "faltantes" => $missingFields
        ], 400);
    }

    // Valores
    $nombre       = trim($data['nombre_producto']);
    $descripcion  = $data['descripcion'] ?? 'N/A';
    $categoria_id = (int)$data['categoria_id'];
    $proveedor_id = $data['proveedor_id'] ?? null;
    $unidad_id    = $data['unidad_medida_id'] ?? null;
    $impuesto_id  = $data['impuesto_id'] ?? null;
    $almacen_id   = $data['almacen_id'] ?? null;
    $cuenta_id    = $data['cuenta_contable_id'] ?? null;
    $precio_compra = (float)$data['precio_compra'];
    $precio_venta  = (float)$data['precio_venta'];
    $stock_actual  = (float)($data['stock_actual'] ?? 0);
    $stock_minimo  = (float)($data['stock_minimo'] ?? 0);
    $activo        = isset($data['activo']) ? ($data['activo'] ? 't' : 'f') : 't';
    $usuarioId = $data['id_usuario'] ?? null;
    $color    = $data['color'] ?? null;

    // Generar código automáticamente si no viene
    if (empty($data['codigo_producto'])) {
        $res = pg_query_params($conn, "SELECT generar_codigo_producto($1) AS codigo", [$categoria_id]);
        $row = pg_fetch_assoc($res);
        $codigo = $row['codigo'] ?? 'N/A';
    } else {
        $codigo = trim($data['codigo_producto']);
    }

    // Insertar producto
    $sql = "INSERT INTO productos 
        (nombre_producto, descripcion, codigo_producto, categoria_id, proveedor_id, unidad_medida_id, impuesto_id, almacen_id, cuenta_contable_id, precio_compra, precio_venta, stock_actual, stock_minimo, activo,color)
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15)
        RETURNING id_producto, nombre_producto, codigo_producto, categoria_id, precio_compra, precio_venta, stock_actual, stock_minimo, activo";

    $params = [$nombre,$descripcion,$codigo,$categoria_id,$proveedor_id,$unidad_id,$impuesto_id,$almacen_id,$cuenta_id,$precio_compra,$precio_venta,$stock_actual,$stock_minimo,$activo,$color];
    
    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        json_response(["success"=>false,"message"=>"Error al crear producto: ".pg_last_error($conn)],500);
    }

    $newProduct = pg_fetch_assoc($result);


     // 🔹 Registrar auditoría (INSERT)
    registrarAuditoria($conn, $usuarioId, 'INSERT', 'productos', null, $newProduct);

    json_response(["success"=>true,"message"=>"Producto creado exitosamente","producto"=>$newProduct]);

} catch(Exception $e) {
    json_response(["success"=>false,"message"=>"Excepción: ".$e->getMessage()],500);
}
