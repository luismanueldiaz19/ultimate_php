<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';
require '../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    if (!isset($_FILES['archivo_excel'])) {
        json_response(["success" => false, "message" => "No se recibió archivo Excel"], 400);
    }

    $spreadsheet = IOFactory::load($_FILES['archivo_excel']['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = [];

    foreach ($sheet->getRowIterator() as $rowIndex => $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        $fila = [];
        foreach ($cellIterator as $cell) {
            $fila[] = trim((string)$cell->getValue());
        }

        $rows[] = $fila;
    }

    $encabezados = array_map('strtolower', $rows[0]);
    unset($rows[0]);

    $resumen = [];

    foreach ($rows as $fila) {
        $data = array_combine($encabezados, $fila);

        // Validación de campos obligatorios
        $requiredFields = ['nombre_producto', 'categoria_id', 'precio_compra', 'precio_venta', 'proveedor_id', 'unidad_medida_id', 'impuesto_id', 'almacen_id', 'cuenta_contable_id', 'id_usuario', 'color'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $resumen[] = [
                "producto" => $data['nombre_producto'] ?? 'Fila sin nombre',
                "estado" => "error",
                "detalle" => "Faltan campos: " . implode(', ', $missingFields)
            ];
            continue;
        }

        // Preparar valores
        $nombre       = trim($data['nombre_producto']);
        $descripcion  = $data['descripcion'] ?? 'N/A';
        $categoria_id = (int)$data['categoria_id'];
        $proveedor_id = (int)$data['proveedor_id'];
        $unidad_id    = (int)$data['unidad_medida_id'];
        $impuesto_id  = (int)$data['impuesto_id'];
        $almacen_id   = (int)$data['almacen_id'];
        $cuenta_id    = (int)$data['cuenta_contable_id'];
        $precio_compra = (float)$data['precio_compra'];
        $precio_venta  = (float)$data['precio_venta'];
        $stock_actual  = (float)($data['stock_actual'] ?? 0);
        $stock_minimo  = (float)($data['stock_minimo'] ?? 0);
        $activo        = isset($data['activo']) ? ($data['activo'] ? 't' : 'f') : 't';
        $usuarioId     = (int)$data['id_usuario'];
        $color         = $data['color'] ?? '#FFFFFF';

        // Generar código si no viene
        if (empty($data['codigo_producto'])) {
            $res = pg_query_params($conn, "SELECT generar_codigo_producto($1) AS codigo", [$categoria_id]);
            $row = pg_fetch_assoc($res);
        }

        // Insertar producto
        $sql = "INSERT INTO productos 
            (nombre_producto, descripcion, codigo_producto, categoria_id, proveedor_id, unidad_medida_id, impuesto_id, almacen_id, cuenta_contable_id, precio_compra, precio_venta, stock_actual, stock_minimo, activo, color)
            VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15)
            RETURNING id_producto";

        $params = [$nombre, $descripcion, $codigo, $categoria_id, $proveedor_id, $unidad_id, $impuesto_id, $almacen_id, $cuenta_id, $precio_compra, $precio_venta, $stock_actual, $stock_minimo, $activo, $color];
        $result = pg_query_params($conn, $sql, $params);

        if ($result) {
            $insertado = pg_fetch_assoc($result);
            auditoria_log($usuarioId, 'insert', 'productos', $insertado['id_producto'], "Producto creado desde Excel: $nombre");
            $resumen[] = ["producto" => $nombre, "estado" => "insertado"];
        } else {
            $resumen[] = ["producto" => $nombre, "estado" => "error", "detalle" => pg_last_error($conn)];
        }
    }

    json_response(["success" => true, "resumen" => $resumen]);
} catch (Exception $e) {
    json_response(["success" => false, "message" => "Error general: " . $e->getMessage()], 500);
}


<!-- 
        // Armar JSON con tu estructura
        $productos[] = [
            "nombre_producto"   => $nombre,
            "precio_compra"     => $compra,
            "precio_venta"      => $venta,
            "precio_2"          => $precio2,
            "precio_3"          => 0, // puedes mapear de otra columna si la tienes
            "codigo_producto"   => "AUTO-" . uniqid(), // ejemplo autogenerado
            "categoria_id"      => 29,
            "proveedor_id"      => 14,
            "unidad_medida_id"  => 2,
            "impuesto_id"       => 5,
            "almacen_id"        => 10,
            "cuenta_contable_id"=> 4
        ];
    }
 -->
