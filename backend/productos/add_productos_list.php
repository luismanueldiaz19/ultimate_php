<?php
include '../conexion.php';
include '../utils.php';
error_reporting(E_ALL & ~E_WARNING);
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $productos = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!is_array($productos) || empty($productos)) {
        json_response(["success" => false, "message" => "Se esperaba una lista de productos"], 400);
    }

    $insertados = [];

    foreach ($productos as $p) {
        $usuarioId = $p['id_usuario'] ?? null;
        // $nombre = trim($p['nombre_producto'] ?? '');
        $categoria_id = $p['categoria_id'] ?? null;

        if (!$usuarioId || !$categoria_id) {
            continue; // Saltar si faltan campos obligatorios
        }

        // Generar código de producto
        $res = pg_query_params($conn, "SELECT public.generar_codigo_producto($1) AS codigo", [$categoria_id]);
        $row = pg_fetch_assoc($res);
        $codigo_producto = $row['codigo'] ?? null;

        if (!$codigo_producto) {
            continue; // Saltar si no se pudo generar código
        }

        // Preparar campos
        $map = [
            'codigo_producto' => $codigo_producto,
            'categoria_id' => $categoria_id,
            'proveedor_id' => $p['proveedor_id'] ?? null,
            'unidad_medida_id' => $p['unidad_medida_id'] ?? null,
            'impuesto_id' => $p['impuesto_id'] ?? null,
            'almacen_id' => $p['almacen_id'] ?? null,
            'cuenta_contable_id' => $p['cuenta_contable_id'] ?? null,
            'productos_catalogos_id' => $p['productos_catalogos_id'] ?? null,
            'costo' => $p['costo'] ?? null,
            'precio_one' => $p['precio_one'] ?? null,
            'precio_two' => $p['precio_two'] ?? null,
            'precio_three' => $p['precio_three'] ?? null,
            'tela' => $p['tela'] ?? null,
            'linea' => $p['linea'] ?? null,
            'material' => $p['material'] ?? null,
            'estilo' => $p['estilo'] ?? null,
            'marca' => $p['marca'] ?? null,
            'genero' => $p['genero'] ?? null,
            'color' => $p['color'] ?? null,
            'size' => $p['size'] ?? null,
            'color_hex' => $p['color_hex'] ?? null,
            'department' => $p['department'] ?? null,
        ];
   // linea, material, estilo, marca, genero, color, size
        $fields = [];
        $placeholders = [];
        $params = [];
        $i = 1;

        foreach ($map as $key => $val) {
            if ($val !== null) {
                $fields[] = $key;
                $placeholders[] = "$$i";
                $params[] = $val;
                $i++;
            }
        }

        $fields[] = 'creado_en';
        $placeholders[] = 'NOW()';

        $sql = "INSERT INTO productos(" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ") RETURNING *";

       $result = @pg_query_params($conn, $sql, $params);

if ($result === false) {
    $error = pg_last_error($conn);
    if (strpos($error, 'duplicate key value') !== false) {
        json_response([
        "success" => false,
        "message" => $error
    ]);
        continue;
    } else {
        // Lanzar otros errores
        throw new Exception($error);
    }
}

    }

    json_response([
        "success" => true,
        "message" => "Productos insertados correctamente"
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage()
    ], 500);
}
?>