<?php
include '../conexion.php'; // Asegúrate de tener $conn configurado
header("Content-Type: application/json");

try {
    $sql = " SELECT 
                pt.plantilla_id,
                pt.nombre_operacion,
                pt.descripcion,
                pc.codigo_cuenta,
				catalogo_cuentas.nombre,
                pc.tipo_movimiento,
                pc.porcentaje,
                pc.monto_fijo,
                pc.orden
            FROM plantilla_contable pt
            JOIN plantilla_cuentas pc ON pt.plantilla_id = pc.plantilla_id
			JOIN catalogo_cuentas ON catalogo_cuentas.codigo = pc.codigo_cuenta
            ORDER BY pt.plantilla_id, pc.orden";

    $res = pg_query($conn, $sql);

    if (!$res) {
        throw new Exception(pg_last_error($conn));
    }

    $agrupado = [];

    while ($row = pg_fetch_assoc($res)) {
        $id = $row['plantilla_id'];

        if (!isset($agrupado[$id])) {
            $agrupado[$id] = [
                'plantilla_id' => $id,
                'nombre_operacion' => $row['nombre_operacion'],
                'descripcion' => $row['descripcion'],
                'cuentas' => []
            ];
        }

        $agrupado[$id]['cuentas'][] = [
            'codigo_cuenta' => $row['codigo_cuenta'],
            'nombre' => $row['nombre'],
            'tipo_movimiento' => $row['tipo_movimiento'],
            'porcentaje' => $row['porcentaje'],
            'monto_fijo' => $row['monto_fijo'],
            'orden' => $row['orden']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => array_values($agrupado)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar plantillas agrupadas',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>