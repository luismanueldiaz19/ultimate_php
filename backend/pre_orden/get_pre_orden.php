<?php
include '../conexion.php';
include '../utils.php';
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true) ?? [];

// Parámetros de paginación
$limit  = isset($data['limit'])  ? intval($data['limit'])  : 10;
$offset = isset($data['offset']) ? intval($data['offset']) : 0;

// Parámetro de filtro (puede ser ficha o número de orden)
$filtro = isset($data['filtro']) ? trim($data['filtro']) : '';

// Base de consulta
$sql = "SELECT 
    p.pre_orden_id,
    p.ficha_id,
	list_ficha_available.ficha, 
	list_ficha_available.color_ficha,
    p.num_orden,
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
    item_pre_orden.item_pre_orden_id,
    item_pre_orden.design_image_id,
    item_pre_orden.id_producto,
	productos.nombre_producto,
	productos.codigo_producto,
    item_pre_orden.nota_producto,
    item_pre_orden.precio,
    item_pre_orden.itbs,
    item_pre_orden.cant,
    item_pre_orden.estado_item,
    item_pre_orden.creado_en AS creado_item,
    clientes.nombre,
	clientes.telefono,
    usuarios.nombre,
	design_images.design_jobs_id,
	design_images.comment_imagen,
	design_images.body_ubicacion,
	design_images.tipo_trabajo,
	design_images.ruta, 
	design_images.tamano
FROM public.pre_orden p
LEFT JOIN public.item_pre_orden ON item_pre_orden.pre_orden_id = item_pre_orden.pre_orden_id
LEFT JOIN public.clientes ON clientes.id_cliente = p.id_cliente
inner JOIN public.usuarios ON usuarios.id_usuario = p.id_usuario
inner JOIN public.productos ON productos.id_producto = item_pre_orden.id_producto
inner JOIN public.list_ficha_available ON list_ficha_available.ficha_id = p.ficha_id
LEFT JOIN public.design_images ON design_images.design_image_id = item_pre_orden.design_image_id
WHERE p.estado_general != 'ENTREGADO'
";


try {
    if (!empty($params)) {
        $res = pg_query_params($conn, $sql, $params);
    } else {
        $res = pg_query($conn, $sql);
    }


    
    $ordenes = pg_fetch_all($res);
     

    $agrupadoPorOrden = [];

foreach ($ordenes as $item) {
    $numOrden = $item['num_orden'];

    // Si la orden no está registrada aún, la inicializamos
    if (!isset($agrupadoPorOrden[$numOrden])) {


        $dataClient = [
        'id_cliente' => $item['id_cliente'],
        'nombre' => $item['nombre'],
        'telefono' => $item['telefono']
       ];

       $dataFichas = [
        'ficha_id' => $item['ficha_id'],
        'ficha' => $item['ficha'],
        'color_ficha' => json_decode($item['color_ficha'], true),
       ];
    
        $agrupadoPorOrden[$numOrden] = [
            'num_orden' => $numOrden,
            'pre_orden_id' => $item['pre_orden_id'],
            'data_ficha' => $dataFichas, 
            'data_cliente' => $dataClient,
            'estado_hoja' => $item['estado_hoja'],
            'estado_general' => $item['estado_general'],
            'fecha_entrega' => $item['fecha_entrega'],
            'creado_en' => $item['creado_en'],
            'total_bruto' => $item['total_bruto'],
            'total_itbis' => $item['total_itbis'],
            'total_final' => $item['total_final'],
            'is_facturado' => $item['is_facturado'],
            'name_logo' => $item['name_logo'],
            'items' => []
        ];
    }
  
    $designImage = [
        'design_image_id' => $item['design_image_id'],
        'design_jobs_id' => $item['design_jobs_id'],
        'comment_imagen' => $item['comment_imagen'],
        'body_ubicacion' => $item['body_ubicacion'],
        'tipo_trabajo' => $item['tipo_trabajo'],
        'ruta' => $item['ruta'],
        'tamano' => $item['tamano']
       ];



    // Agregamos el ítem a la orden correspondiente
    $agrupadoPorOrden[$numOrden]['items'][] = [
        'item_pre_orden_id' => $item['item_pre_orden_id'],
        'id_producto' => $item['id_producto'],
        'nombre_producto' => $item['nombre_producto'],
        'codigo_producto' => $item['codigo_producto'],
        'nota_producto' => $item['nota_producto'],
        'precio' => $item['precio'],
        'itbs' => $item['itbs'],
        'cant' => $item['cant'],
        'estado_item' => $item['estado_item'],
        'creado_item' => $item['creado_item'],
        'DesignImage' => $designImage,

    ];
}


    // $ordenes = pg_fetch_all($res);

    echo json_encode([
        "success" => true,
        "data" => array_values($agrupadoPorOrden),
        "limit" => $limit,
        "offset" => $offset
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
