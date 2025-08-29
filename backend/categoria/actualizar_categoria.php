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

    if (!$id) {
        json_response(["success" => false, "message" => "ID de categoría requerido"], 400);
    }

    $nombre = trim($data['nombre_categoria'] ?? '');
    $descripcion = trim($data['descripcion_categoria'] ?? 'N/A');
    $abreviado = trim($data['abreviado'] ?? '');
    $status = isset($data['status_categoria']) ? ($data['status_categoria'] ? 't' : 'f') : 't';

    // Verificar duplicado en otro registro
    $check = pg_query_params($conn, "SELECT 1 FROM categorias WHERE nombre_categoria = $1 AND id_categoria != $2 LIMIT 1", [$nombre, $id]);

    if (pg_num_rows($check) > 0) {
        json_response(["success" => false, "message" => "El nombre de la categoría ya existe"], 409);
    }
         // Obtener datos para auditoría
      $res = pg_query_params($conn, "SELECT * FROM categorias WHERE id_categoria = $1", [$id]);

      $datos_anteriores = pg_fetch_assoc($res);

      if (!$datos_anteriores) {
            json_response(['success' => false, 'message' => 'categorias no encontrado'], 404); 

        }

    $sql = "UPDATE categorias 
            SET nombre_categoria = $1, descripcion_categoria = $2, abreviado = $3, status_categoria = $4, actualizado_en = NOW()
            WHERE id_categoria = $5
            RETURNING id_categoria, nombre_categoria, descripcion_categoria, abreviado, status_categoria, creado_en, actualizado_en";
    $params = [$nombre, $descripcion, $abreviado, $status, $id];

    $result = @pg_query_params($conn, $sql, $params);

    



      

    if (!$result) {
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al actualizar categoría: " . $error], 500);
    }

        registrarAuditoria($conn, $usuarioId, 'UPDATE', 'categorias', $datos_anteriores, $data);

    $updatedCategoria = pg_fetch_assoc($result);
    json_response(["success" => true, "message" => "Categoría actualizada exitosamente", "categoria" => $updatedCategoria]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
