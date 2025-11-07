<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';
    include '../utils.php';

    $input = json_decode(file_get_contents('php://input'), true);

    $fecha_inicio = $input['fecha_inicio'] ?? null;
    $fecha_fin    = $input['fecha_fin'] ?? null;
    $almacen_id   = $input['almacen_id'] ?? null;

    if (!$fecha_inicio || !$fecha_fin) {
        echo json_encode([
            'success' => false,
            'message' => 'Debe enviar rango de fechas (fecha_inicio y fecha_fin)'
        ]);
        exit;
    }

    // Consulta base de pérdidas
    $query = "
        SELECT 
            ip.inventario_perdidas_id,
            ip.producto_id AS id_producto,
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
            ip.cantidad,
            ip.costo_unitario,
            ip.total_perdida,
            ip.referencia,
            ip.motivo,
            ip.creado_por,
            ip.creado_en::date AS fecha
        FROM public.inventario_perdidas ip
        INNER JOIN public.productos p ON p.id_producto = ip.producto_id
        INNER JOIN public.productos_catalogos c ON c.productos_catalogos_id = p.productos_catalogos_id
        WHERE ip.creado_en::date BETWEEN $1 AND $2
    ";

    $params = [$fecha_inicio, $fecha_fin];

    // Si se filtra por almacén
    if (!empty($almacen_id)) {
        $query .= " AND ip.almacen_id = $3";
        $params[] = $almacen_id;
    }

    $query .= " ORDER BY p.material, ip.creado_en ASC";

    $result = pg_query_params($conn, $query, $params);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al consultar pérdidas: ' . pg_last_error($conn)
        ]);
        exit;
    }

    // Agrupar por producto
    $data = [];
    while ($row = pg_fetch_assoc($result)) {
        $pid = $row['id_producto'];

        if (!isset($data[$pid])) {
            $data[$pid] = [
                'producto' => [
                    'id_producto' => $pid,
                    'codigo_producto' => $row['codigo_producto'],
                    'material' => $row['material'],
                    'linea' => $row['linea'],
                    'estilo' => $row['estilo'],
                    'marca' => $row['marca'],
                    'genero' => $row['genero'],
                    'color' => $row['color'],
                    'size' => $row['size']
                ],
                'catalogos' => [
                    'ruta_imagen' => $row['ruta_imagen'],
                    'nombre_catalogo' => $row['nombre_catalogo']
                ],
                'perdidas' => []
            ];
        }

        $data[$pid]['perdidas'][] = [
            'fecha' => $row['fecha'],
            'cantidad' => $row['cantidad'],
            'costo_unitario' => $row['costo_unitario'],
            'total_perdida' => $row['total_perdida'],
            'motivo' => $row['motivo'],
            'referencia' => $row['referencia'],
            'creado_por' => $row['creado_por']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => array_values($data)
    ]);

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}
?>
