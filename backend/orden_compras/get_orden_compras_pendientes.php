<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    include '../conexion.php';
    include '../utils.php';

    $query = "
        SELECT 
            oc.orden_compra_id,
            oc.fecha_aprobacion,
            oc.num_factura,
            oc.fecha,
            oc.estado,
            oc.observaciones,
            
            p.id_proveedor,
            p.nombre_proveedor,
            p.contacto_proveedor,
            p.telefono_proveedor,
            p.email_proveedor,
            p.direccion_proveedor,

            d.orden_compra_detalle_id,


            d.id_producto,
            d.nota_producto,
            pr.codigo_producto,
            pr.cuenta_costo,
            pr.cuenta_ingreso,
            d.cantidad,
            d.costo_unitario as costo,
            d.total

        FROM orden_compra oc
        JOIN proveedores p ON oc.id_proveedor = p.id_proveedor
        JOIN orden_compra_detalle d ON oc.orden_compra_id = d.orden_compra_id
        JOIN productos pr ON d.id_producto = pr.id_producto
        WHERE oc.estado = 'pendiente'
        ORDER BY oc.fecha DESC
    ";

    $result = pg_query($conn, $query);

    if (!$result) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al consultar: ' . pg_last_error($conn)
        ]);
        exit;
    }

    $ordenes = [];

    while ($row = pg_fetch_assoc($result)) {
        $orden_id = $row['orden_compra_id'];

        // Si la orden aún no está en el array, la creamos
        if (!isset($ordenes[$orden_id])) {
            $ordenes[$orden_id] = [
                'orden_compra_id' => $orden_id,
                'num_factura' => $row['num_factura'],
                'fecha_aprobacion' => $row['fecha_aprobacion'],
                'fecha' => $row['fecha'],
                'estado' => $row['estado'],
                'observaciones' => $row['observaciones'],
                'proveedor' => [
                    'id_proveedor' => $row['id_proveedor'],
                    'nombre_proveedor' => $row['nombre_proveedor'],
                    'contacto_proveedor' => $row['contacto_proveedor'],
                    'telefono_proveedor' => $row['telefono_proveedor'],
                    'email_proveedor' => $row['email_proveedor'],
                    'direccion_proveedor' => $row['direccion_proveedor']
                ],
                'detalles_compras' => []
            ];
        }
        // Agregamos el producto a la orden correspondiente
        $ordenes[$orden_id]['detalles_compras'][] = [
            'orden_compra_detalle_id' => $row['orden_compra_detalle_id'],
            'producto' => [
            'id_producto' => $row['id_producto'],
            'nota_producto' => $row['nota_producto'],
            'codigo_producto' => $row['codigo_producto'],
            'costo' => $row['costo'],
            'cuenta_costo' => $row['cuenta_costo'],
            'cuenta_ingreso' => $row['cuenta_ingreso'],
            'cantidad' => $row['cantidad'],
            'costo' => $row['costo'],
            'total' => $row['total']  
            ],
           
        ];
    }

    // Reindexar para que sea un array plano
    $ordenes = array_values($ordenes);

    echo json_encode([
        'success' => true,
        'orden' => $ordenes
    ]);

    pg_close($conn);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, "message" => "Método no permitido"]);
}
?>
