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

    // Consulta base
    $query = "
        SELECT 
            p.id_producto,
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
            m.tipo_movimiento,
            m.cantidad,
            m.costo_unitario,
            m.motivo,
            m.referencia,
            m.creado_por,
            m.creado_en::date AS fecha
        FROM public.inventario_movimientos m
        INNER JOIN public.productos p ON p.id_producto = m.producto_id
		INNER JOIN public.productos_catalogos c ON c.productos_catalogos_id = p.productos_catalogos_id
        WHERE m.creado_en::date BETWEEN $1 AND $2";

    $params = [$fecha_inicio, $fecha_fin];

    // Si se envía un almacén específico
    if (!empty($almacen_id)) {
        $query .= " AND m.almacen_id = $3";
        $params[] = $almacen_id;
    }

    $query .= " ORDER BY p.material, m.creado_en ASC limit 1500";

    $result = pg_query_params($conn, $query, $params);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al consultar movimientos: ' . pg_last_error($conn)
        ]);
        exit;
    }

    // Agrupar manualmente en PHP
    $data = pg_fetch_all($result) ?: [];

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
