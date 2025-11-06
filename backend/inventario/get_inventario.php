<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    try {
        // Consulta del inventario actual (producto + catálogo + inventario)
        $sql = "SELECT 
            i.id_inventario,
            i.producto_id,
            p.productos_catalogos_id,
            c.ruta_imagen,
            c.nombre_catalogo,
            p.codigo_producto,
            p.material,
            p.linea,
            p.estilo,
            p.marca,
            p.genero,
            p.color,
            p.size,
            i.stock_actual,
            i.stock_minimo,
            i.stock_maximo,
            i.costo_promedio,
            (i.stock_actual * i.costo_promedio) AS valor_total,
            i.actualizado_en::date AS fecha_actualizacion  FROM public.inventario i
            INNER JOIN public.productos p ON p.id_producto = i.producto_id
            INNER JOIN public.productos_catalogos c ON c.productos_catalogos_id = p.productos_catalogos_id
            ORDER BY c.nombre_catalogo ASC, p.codigo_producto ASC";

    $result = pg_query($conn, $sql);

    if (!$result) {
        throw new Exception('Error al ejecutar la consulta: ' . pg_last_error($conn));
    }

    $data = [];
    while   ($row = pg_fetch_assoc($result)) {
             $producto = [
                    'id_producto' => $row['producto_id'],
                    'codigo_producto' => $row['codigo_producto'],
                    'material' => $row['material'],
                    'linea' => $row['linea'],
                    'estilo' => $row['estilo'],
                    'marca' => $row['marca'],
                    'genero' => $row['genero'],
                    'color' => $row['color'],
                    'size' => $row['size']
                ];
                $catalogos = [
                    'productos_catalogos_id' => $row['productos_catalogos_id'],
                    'ruta_imagen' => $row['ruta_imagen'],
                    'nombre_catalogo' => $row['nombre_catalogo']
                ];
          
        $data[] = [
            'id_inventario'         => $row['id_inventario'],
            'stock_actual'          => $row['stock_actual'],
            'stock_minimo'          => $row['stock_minimo'],
            'stock_maximo'          => $row['stock_maximo'],
            'costo_promedio'        => $row['costo_promedio'],
            'valor_total'           => $row['valor_total'],
            'fecha_actualizacion'   => $row['fecha_actualizacion'],
            'catalogos'             => $catalogos,
            'producto'             => $producto,
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Inventario obtenido correctamente',
        'data'    => $data
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

pg_close($conn);


} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}
?>
