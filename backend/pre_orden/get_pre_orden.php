<?php
include '../conexion.php';
include '../utils.php';
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true) ?? [];

// Parámetros de paginación
$limit  = isset($data['limit'])  ? intval($data['limit'])  : 10;
$offset = isset($data['offset']) ? intval($data['offset']) : 0;
$filtro = isset($data['filtro']) ? trim($data['filtro']) : '';

// Consulta base
$sql = "SELECT 
    p.pre_orden_id,
    p.ficha_id,
    list_ficha_available.ficha, 
    list_ficha_available.color_ficha,
    p.num_comprobante,
    p.fecha_emision,
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
    p.nota_orden,
    item_pre_orden.item_pre_orden_id,
    item_pre_orden.id_producto,
    productos.nombre_producto,
    productos.codigo_producto,
    productos.department,
	productos.tela,
    productos.cuenta_contable_id,
    item_pre_orden.is_produccion,
    item_pre_orden.nota_producto,
    item_pre_orden.precio,
    item_pre_orden.itbs,
    item_pre_orden.cant,
    item_pre_orden.estado_item,
    item_pre_orden.creado_en AS creado_item,
    item_pre_orden.design_tipo_id,
    clientes.nombre as nombre_cliente,
    clientes.telefono,
    usuarios.nombre as usuario_nombre,
    design_tipo.tipo_trabajo,
    design_images_items.design_images_items_id,
    design_images_items.comment_imagen, 
    design_images_items.body_ubicacion,
    design_images_items.ruta
FROM public.pre_orden p 
INNER JOIN public.item_pre_orden ON item_pre_orden.pre_orden_id = p.pre_orden_id
INNER JOIN public.clientes ON clientes.id_cliente = p.id_cliente
INNER JOIN public.usuarios ON usuarios.id_usuario = p.id_usuario
INNER JOIN public.productos ON productos.id_producto = item_pre_orden.id_producto
INNER JOIN public.list_ficha_available  ON list_ficha_available.ficha_id = p.ficha_id
INNER JOIN public.design_tipo ON design_tipo.design_tipo_id = item_pre_orden.design_tipo_id
INNER JOIN public.design_images_items ON design_images_items.design_tipo_id = item_pre_orden.design_tipo_id
WHERE p.estado_general != 'ENTREGADO' 
ORDER BY p.num_orden ASC";

try {
    $res = pg_query($conn, $sql);
    $ordenes = pg_fetch_all($res);

    $agrupadoPorOrden = [];

    foreach ($ordenes as $item) {
        $numOrden = $item['num_orden'];
        $designId = $item['design_tipo_id'];

        // Si la orden no está registrada aún, la inicializamos
        if (!isset($agrupadoPorOrden[$numOrden])) {
            //   p.num_comprobante,
    // p.fecha_emision,
            $agrupadoPorOrden[$numOrden] = [
                'num_orden' => $numOrden,
                'num_comprobante' =>  $item['num_comprobante'],
                'fecha_emision' =>  $item['fecha_emision'],
                'pre_orden_id' => $item['pre_orden_id'],
                'ficha' => [
                    'ficha_id' => $item['ficha_id'],
                    'ficha' => $item['ficha'],
                    'color_ficha' => json_decode($item['color_ficha'], true),
                ],
                'cliente' => [
                    'id_cliente' => $item['id_cliente'],
                    'nombre' => $item['nombre_cliente'],
                    'telefono' => $item['telefono']
                ],
                'usuario' => [
                    'id_usuario' => $item['id_usuario'],
                    'usuario_nombre' => $item['usuario_nombre']
                ],
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
                'items_pre_orden' => []
            ];
        }

        // Buscar si el item ya existe dentro de esta orden
        $itemIndex = array_search($item['item_pre_orden_id'], array_column($agrupadoPorOrden[$numOrden]['items_pre_orden'], 'item_pre_orden_id'));

        if ($itemIndex === false) {
            $agrupadoPorOrden[$numOrden]['items_pre_orden'][] = [
                'item_pre_orden_id' => $item['item_pre_orden_id'],
                'is_produccion' => $item['is_produccion'],
                'id_producto' => $item['id_producto'],
                'nombre_producto' => $item['nombre_producto'],
                'codigo_producto' => $item['codigo_producto'],
                'nota_producto' => $item['nota_producto'],
                'department' => $item['department'],
                'tela' => $item['tela'],
                'precio' => $item['precio'],
                'itbs' => $item['itbs'],
                'cant' => $item['cant'],
                'estado_item' => $item['estado_item'],
                'creado_item' => $item['creado_item'],
                'designTipo' => []
            ];
            $itemIndex = count($agrupadoPorOrden[$numOrden]['items_pre_orden']) - 1;
        }

        // Referencia al designTipo del item actual
        $designsRef = &$agrupadoPorOrden[$numOrden]['items_pre_orden'][$itemIndex]['designTipo'];
        $designIndex = array_search($designId, array_column($designsRef, 'design_tipo_id'));

        if ($designIndex === false) {
            $designsRef[] = [
                "design_tipo_id" => $designId,
                "tipo_trabajo" => $item['tipo_trabajo'],
                "designImagesItems" => []
            ];
            $designIndex = count($designsRef) - 1;
        }

        // Agregar imagen
        $designsRef[$designIndex]['designImagesItems'][] = [
            "design_images_items_id" => $item['design_images_items_id'],
            "comment_imagen" => $item['comment_imagen'],
            "body_ubicacion" => $item['body_ubicacion'],
            "ruta" => $item['ruta']
        ];
    }

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
