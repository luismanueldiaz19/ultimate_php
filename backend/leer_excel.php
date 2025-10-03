<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';
require '../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $rutaArchivo = __DIR__ . '/archivos/datos.xlsx';

    if (!file_exists($rutaArchivo)) {
        json_response(["success" => false, "message" => "El archivo no existe en la ruta especificada"], 400);
    }

    $spreadsheet = IOFactory::load($rutaArchivo);
    
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

    // Parámetros fijos
    $categoria_id     = 14;
    $proveedor_id     = 14;
    $unidad_medida_id = 2;
    $impuesto_id      = 5;
    $almacen_id       = 10;
    $cuenta_contable_id = 4;
    $usuarioId        = 1;

    foreach ($rows as $fila) {
        $data = array_combine($encabezados, $fila);

        // Validar campos dinámicos
        $requiredFields = ['nombre_producto', 'precio_compra', 'precio_venta'];
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

        // Extraer valores dinámicos
        $nombre_producto = trim($data['nombre_producto']);
        $precio_compra   = (float)$data['precio_compra'];
        $precio_venta    = (float)$data['precio_venta'];
        $precio_2        = isset($data['precio_2']) ? (float)$data['precio_2'] : 0.0;
        $precio_3        = isset($data['precio_3']) ? (float)$data['precio_3'] : 0.0;

        // Generar código automáticamente
        $res = pg_query_params($conn, "SELECT generar_codigo_producto($1) AS codigo", [$categoria_id]);
        $row = pg_fetch_assoc($res);
        $codigo_producto = $row['codigo'] ?? null;

        if (empty($codigo_producto)) {
            $resumen[] = [
                "producto" => $nombre_producto,
                "estado" => "error",
                "detalle" => "No se pudo generar código de producto"
            ];
            continue;
        }

        // Insertar producto
        $sql = "INSERT INTO public.productos (
            categoria_id, proveedor_id, unidad_medida_id, impuesto_id, almacen_id, cuenta_contable_id,
            codigo_producto, nombre_producto, precio_compra, precio_venta, precio_2, precio_3
        ) VALUES (
            $1, $2, $3, $4, $5, $6,
            $7, $8, $9, $10, $11, $12
        ) RETURNING id_producto";

        $params = [
            $categoria_id, $proveedor_id, $unidad_medida_id, $impuesto_id, $almacen_id, $cuenta_contable_id,
            $codigo_producto, $nombre_producto, $precio_compra, $precio_venta, $precio_2, $precio_3
        ];

        $result = pg_query_params($conn, $sql, $params);

        if ($result) {
            $insertado = pg_fetch_assoc($result);
            auditoria_log($usuarioId, 'insert', 'productos', $insertado['id_producto'], "Producto creado desde Excel: $nombre_producto");
            $resumen[] = ["producto" => $nombre_producto, "estado" => "insertado"];
        } else {
            $resumen[] = ["producto" => $nombre_producto, "estado" => "error", "detalle" => pg_last_error($conn)];
        }
    }

    json_response(["success" => true, "resumen" => $resumen]);
} catch (Exception $e) {
    json_response(["success" => false, "message" => "Error general: " . $e->getMessage()], 500);
}