<?php
include '../conexion.php';
include '../utils.php';

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
        $nombre = trim($p['nombre_producto'] ?? '');
        $categoria_id = $p['categoria_id'] ?? null;

        if (!$usuarioId || !$nombre || !$categoria_id) {
            continue; // Saltar si faltan campos obligatorios
        }

        // Generar código de producto
        $res = pg_query_params($conn, "SELECT generar_codigo_producto($1) AS codigo", [$categoria_id]);
        $row = pg_fetch_assoc($res);
        $codigo_producto = $row['codigo'] ?? null;

        if (!$codigo_producto) {
            continue; // Saltar si no se pudo generar código
        }

        // Preparar campos
        $map = [
            'nombre_producto' => $nombre,
            'codigo_producto' => $codigo_producto,
            'categoria_id' => $categoria_id,
            'proveedor_id' => $p['proveedor_id'] ?? null,
            'unidad_medida_id' => $p['unidad_medida_id'] ?? null,
            'impuesto_id' => $p['impuesto_id'] ?? null,
            'almacen_id' => $p['almacen_id'] ?? null,
            'cuenta_contable_id' => $p['cuenta_contable_id'] ?? null,
            'costo' => $p['costo'] ?? null,
            'precio_one' => $p['precio_one'] ?? null,
            'precio_two' => $p['precio_two'] ?? null,
            'precio_three' => $p['precio_three'] ?? null,
            'tela' => $p['tela'] ?? null,
            'department' => $p['department'] ?? null
        ];

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

        $sql = "INSERT INTO productos (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ") RETURNING *";

        $result = pg_query_params($conn, $sql, $params);
        if ($result) {
            $insertados[] = pg_fetch_assoc($result);
        }
    }

    json_response([
        "success" => true,
        "message" => "Productos insertados correctamente",
        "insertados" => $insertados
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage()
    ], 500);
}
?>