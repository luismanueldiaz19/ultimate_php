<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];


    if (!isset($data['id_proveedor']) || !is_numeric($data['id_proveedor'])) {
        json_response(["success" => false, "message" => "El id del proveedor es obligatorio y debe ser numérico"], 400);
    }

    $id = (int)$data['id_proveedor'];
    $usuarioId = $data['id_usuario'];

    // Verificar existencia
    $check = pg_query_params($conn, "SELECT 1 FROM proveedores WHERE id_proveedor = $1 LIMIT 1", [$id]);

    if (pg_num_rows($check) === 0) {
        json_response(["success" => false, "message" => "El proveedor no existe"], 404);
    }



    // Obtener datos para auditoría
    $res = pg_query_params($conn, "SELECT * FROM proveedores WHERE id_proveedor = $1 LIMIT 1", [$id]);

    $datos_anteriores = pg_fetch_assoc($res);

    if (!$datos_anteriores) {
    json_response(['success' => false, 'message' => 'Proveedor no encontrado'], 404);
     }
 
 


    // Eliminar
    $result = pg_query_params($conn, "DELETE FROM proveedores WHERE id_proveedor = $1", [$id]);

    if (!$result) {
        json_response(["success" => false, "message" => "Error al eliminar: " . pg_last_error($conn)], 500);
    }
    
    

    registrarAuditoria($conn, $usuarioId, 'DELETE', 'proveedores', $datos_anteriores, null);


    json_response(["success" => true, "message" => "Proveedor eliminado correctamente"]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
