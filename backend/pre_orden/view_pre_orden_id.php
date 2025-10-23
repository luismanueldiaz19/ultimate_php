<?php
include '../conexion.php';
include '../utils.php';
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

// Filtro recibido
$numOrdenFiltro = isset($data['num_orden']) ? trim($data['num_orden']) : '';

// Validar campos requeridos
$params = ['num_orden'];
$faltantes = [];

foreach ($params as $campo) {
    if (!isset($data[$campo]) || trim($data[$campo]) === '') {
        $faltantes[] = $campo;
    }
}

if (!empty($faltantes)) {
    echo json_encode([
        'status' => false,
        'message' => 'Faltan campos requeridos o están vacíos',
        'faltantes' => $faltantes
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Base de consulta
$sql = "SELECT 
    p.pre_orden_id,
    p.ficha_id,
    list_ficha_available.ficha, 
    list_ficha_available.color_ficha,
    p.num_orden,
    p.num_comprobante,
    p.fecha_emision,
    p.name_logo,
    p.id_usuario,
    p.id_cliente,
    p.estado_hoja,
    p.estado_general,
    p.creado_en,
    p.fecha_entrega,
    p.total_bruto,
    p.total_itbis,
    p.total_final,
    p.is_facturado,
    p.name_logo,
    p.nota_orden,
    item_pre_orden.item_pre_orden_id,
    item_pre_orden.id_producto,



    productos.codigo_producto,
    productos.department,
    productos.cuenta_contable_id,
    productos.linea,
    productos.material,
    productos.estilo,
    productos.marca,
    productos.genero,
    productos.color,
    productos.size,




    item_pre_orden.nota_producto,
    item_pre_orden.precio,
    item_pre_orden.itbs,
    item_pre_orden.cant,
    item_pre_orden.estado_item,
    item_pre_orden.creado_en AS creado_item,
    item_pre_orden.is_produccion,
    clientes.nombre as nombre_cliente,
    clientes.telefono,
    clientes.codigo_cuenta_cxc,
    clientes.tiene_credito, 
    clientes.limite_credito,
    clientes.dias_credito,
    usuarios.nombre as usuario_nombre,

    pagos.pago_id,
    pagos.monto_pago,
    pagos.metodo_pago,
    pagos.referencia_pago,
    pagos.fecha_pago,
    pagos.observacion,

    -- Estado de pago agregado
    COALESCE(pagos_estado.total_pagado, 0) AS total_pagado,
    (p.total_final - COALESCE(pagos_estado.total_pagado, 0)) AS pendiente,
    CASE 
        WHEN COALESCE(pagos_estado.total_pagado, 0) >= p.total_final THEN 'COMPLETO'
        WHEN COALESCE(pagos_estado.total_pagado, 0) > 0 THEN 'PARCIAL'
        ELSE 'SIN PAGOS'
    END AS estado_pago


    
FROM public.pre_orden p
LEFT JOIN public.item_pre_orden ON item_pre_orden.pre_orden_id = p.pre_orden_id
LEFT JOIN public.clientes ON clientes.id_cliente = p.id_cliente
INNER JOIN public.usuarios ON usuarios.id_usuario = p.id_usuario
INNER JOIN public.productos ON productos.id_producto = item_pre_orden.id_producto
INNER JOIN public.list_ficha_available ON list_ficha_available.ficha_id = p.ficha_id
-- JOIN pagos individuales
LEFT JOIN public.pagos_pre_orden pagos ON pagos.pre_orden_id = p.pre_orden_id

-- JOIN estado de pago agregado
LEFT JOIN (
    SELECT 
        pre_orden_id,
        SUM(monto_pago) AS total_pagado
    FROM public.pagos_pre_orden
    GROUP BY pre_orden_id
) pagos_estado ON pagos_estado.pre_orden_id = p.pre_orden_id
WHERE p.num_orden = $1";

$params = [$numOrdenFiltro];

try {
    $res = pg_query_params($conn, $sql, $params);

    if (!$res) {
        throw new Exception(pg_last_error($conn));
    }

    $ordenes = pg_fetch_all($res) ?: [];

    $agrupadoPorOrden = [];

    foreach ($ordenes as $item) {
        $numOrden = $item['num_orden'];

        // Si la orden no está registrada aún, la inicializamos
        if (!isset($agrupadoPorOrden[$numOrden])) {
            $dataClient = [
                'id_cliente' => $item['id_cliente'],
                'nombre' => $item['nombre_cliente'],
                'telefono' => $item['telefono'],
                'codigo_cuenta_cxc' => $item['codigo_cuenta_cxc'],
                'tiene_credito' => $item['tiene_credito'],
                'limite_credito' => $item['limite_credito'],
                'dias_credito' => $item['dias_credito']
            ];

       
            $dataFichas = [
                'ficha_id' => $item['ficha_id'],
                'ficha' => $item['ficha'],
                'color_ficha' => json_decode($item['color_ficha'], true),
            ];

            $dataUsuario = [
                'id_usuario' => $item['id_usuario'],
                'usuario_nombre' => $item['usuario_nombre'],
            ];

            $agrupadoPorOrden[$numOrden] = [
                'num_orden' => $numOrden,
                'num_comprobante' =>  $item['num_comprobante'],
                'fecha_emision' =>  $item['fecha_emision'],
                'pre_orden_id' => $item['pre_orden_id'],
                'ficha' => $dataFichas,
                'cliente' => $dataClient,
                'usuario' => $dataUsuario,
                'estado_hoja' => $item['estado_hoja'],
                'estado_general' => $item['estado_general'],
                'fecha_entrega' => $item['fecha_entrega'],
                'creado_en' => $item['creado_en'],
                'total_bruto' => $item['total_bruto'],
                'total_itbis' => $item['total_itbis'],
                'total_final' => $item['total_final'],
                'is_facturado' => $item['is_facturado'],
                'name_logo' => $item['name_logo'],
                'nota_orden' => $item['nota_orden'],
                'items_pre_orden' => [],
                'pagos' => [],
                'estado_pago' => $item['estado_pago'] ?? null,
                'total_pagado' => $item['total_pagado'] ?? 0,
                'pendiente' => $item['pendiente'] ?? 0,
            ];
        }

        // Evitar duplicados de productos
        $idItem = $item['item_pre_orden_id'];
        $yaExiste = array_filter($agrupadoPorOrden[$numOrden]['items_pre_orden'], function ($i) use ($idItem) {
            return $i['item_pre_orden_id'] == $idItem;
        });

        if (empty($yaExiste) && !empty($idItem)) {
            
            $designImage = [
                // 'design_image_id' => $item['design_image_id'],
                // 'design_jobs_id' => $item['design_jobs_id'],
                // 'comment_imagen' => $item['comment_imagen'],
                // 'body_ubicacion' => $item['body_ubicacion'],
                // 'tipo_trabajo' => $item['tipo_trabajo'],
                // 'ruta' => $item['ruta'],
                // 'tamano' => $item['tamano']
            ];

            $dataProducto = [
                'codigo_producto' => $item['codigo_producto'],
                'id_producto' => $item['id_producto'],
                "linea" => $item['linea'],
                "material" => $item['material'],
                "estilo" => $item['estilo'],
                "marca" => $item['marca'],
                "genero" => $item['genero'],
                "color" => $item['color'],
                "size" => $item['size'],
                'nota_producto' => $item['nota_producto'],
            ];

            $agrupadoPorOrden[$numOrden]['items_pre_orden'][] = [
                'item_pre_orden_id' => $idItem,
                'producto' =>  $dataProducto,
                'precio' => $item['precio'],
                'itbs' => $item['itbs'],
                'cant' => $item['cant'],
                'estado_item' => $item['estado_item'],
                'creado_item' => $item['creado_item'],
                'is_produccion' => $item['is_produccion'],
                'department' => $item['department'],
                'DesignImage' => $designImage,
            ];
        }

       $idPago = $item['pago_id'];
$yaExistePago = array_filter($agrupadoPorOrden[$numOrden]['pagos'], function ($p) use ($idPago) {
    return $p['pago_id'] == $idPago;
});

if (empty($yaExistePago) && !empty($idPago)) {
    $agrupadoPorOrden[$numOrden]['pagos'][] = [
        'pago_id' => $item['pago_id'],
        'monto_pago' => $item['monto_pago'],
        'metodo_pago' => $item['metodo_pago'],
        'referencia_pago' => $item['referencia_pago'],
        'fecha_pago' => $item['fecha_pago'],
        'observacion' => $item['observacion'],
        'id_usuario' => $item['id_usuario'],
    ];
}


    }

    echo json_encode([
        "success" => true,
        "pre_orden" => reset($agrupadoPorOrden)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
