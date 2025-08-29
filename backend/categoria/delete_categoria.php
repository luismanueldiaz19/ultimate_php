<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

header("Content-Type: application/json; charset=UTF-8");

header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];
    $id = $data['id_categoria'] ?? null;
    $usuarioId = $data['id_usuario'] ?? null;
    // id_usuario

    if (!$id) {
        json_response(["success" => false, "message" => "ID de categoría requerido"], 400);
    }

    // Verificar existencia
    $check = pg_query_params($conn, "SELECT nombre_categoria FROM categorias WHERE id_categoria = $1 LIMIT 1", [$id]);
    if (pg_num_rows($check) === 0) {
        json_response(["success" => false, "message" => "La categoría no existe"], 404);
    }
    $categoria = pg_fetch_assoc($check);




     // Obtener datos para auditoría
$res = pg_query_params($conn, "SELECT * FROM categorias WHERE id_categoria = $1", [$id]);

$datos_anteriores = pg_fetch_assoc($res);

if (!$datos_anteriores) {
    json_response(['success' => false, 'message' => 'categorias no encontrado'], 404);
}
      
     

    // Eliminar
    $sql = "DELETE FROM categorias WHERE id_categoria = $1";
    $result = @pg_query_params($conn, $sql, [$id]);

    if (!$result) {
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al eliminar categoría: " . $error], 500);
    }

    if (!$result) {
    json_response([
        "success" => false,
        "message" => "No se pudo eliminar la categoría"
    ], 200);
}


    
    registrarAuditoria($conn, $usuarioId, 'DELETE', 'categorias', $datos_anteriores, null);

    json_response(["success" => true, "message" => "Categoría eliminada exitosamente", "categoria" => $categoria]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
