<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!isset($data['productos_catalogos_id']) || !is_numeric($data['productos_catalogos_id'])) {
        json_response(["success" => false, "message" => "ID inválido"], 400);
    }

    $id = intval($data['productos_catalogos_id']);
    $nombre = trim($data['nombre_catalogo'] ?? '');
    $descripcion = trim($data['descripcion_catalogo'] ?? '');
    $ruta = trim($data['ruta_imagen'] ?? '');
    $precio = isset($data['precio']) ? floatval($data['precio']) : null;
    $activo = isset($data['activo']) ? ($data['activo'] ? 't' : 'f') : null;

    $sql = "UPDATE productos_catalogos 
            SET nombre_catalogo = COALESCE(NULLIF($1, ''), nombre_catalogo),
                descripcion_catalogo = COALESCE($2, descripcion_catalogo),
                ruta_imagen = COALESCE(NULLIF($3, ''), ruta_imagen),
                precio = COALESCE($4, precio),
                activo = COALESCE($5, activo)
            WHERE productos_catalogos_id = $6
            RETURNING *";
    $params = [$nombre, $descripcion, $ruta, $precio, $activo, $id];

    $result = pg_query_params($conn, $sql, $params);

    if (!$result || pg_num_rows($result) === 0) {
        json_response(["success" => false, "message" => "No se pudo actualizar el producto"], 404);
    }

    $updated = pg_fetch_assoc($result);
    json_response(["success" => true, "message" => "Producto actualizado", "producto" => $updated]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
