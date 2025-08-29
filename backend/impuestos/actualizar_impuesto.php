<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Campo obligatorio: id_impuesto
    if (!isset($data['id_impuesto']) || !is_numeric($data['id_impuesto'])) {
        json_response(["success" => false, "message" => "El id del impuesto es obligatorio y debe ser numérico"], 400);
    }

    $id = (int)$data['id_impuesto'];

    // Campos que se pueden actualizar
    $nombre = isset($data['nombre_impuesto']) ? trim($data['nombre_impuesto']) : null;
    $porcentaje = isset($data['porcentaje_impuesto']) ? (float)$data['porcentaje_impuesto'] : null;
    $statu = isset($data['statu_impuesto']) ? ($data['statu_impuesto'] ? 't' : 'f') : null;

    if ($nombre === null && $porcentaje === null && $statu === null) {
        json_response(["success" => false, "message" => "No se enviaron campos para actualizar"], 400);
    }

    // Verificar duplicado de nombre
    if ($nombre !== null) {
        $check = pg_query_params($conn, "SELECT 1 FROM impuestos WHERE nombre_impuesto = $1 AND id_impuesto <> $2 LIMIT 1", [$nombre, $id]);
        if (pg_num_rows($check) > 0) {
            json_response(["success" => false, "message" => "El nombre del impuesto ya existe"], 409);
        }
    }

    // Construir SQL dinámico
    $setParts = [];
    $params = [];
    $i = 1;

    if ($nombre !== null) { $setParts[] = "nombre_impuesto = $" . $i++; $params[] = $nombre; }
    if ($porcentaje !== null) { $setParts[] = "porcentaje_impuesto = $" . $i++; $params[] = $porcentaje; }
    if ($statu !== null) { $setParts[] = "statu_impuesto = $" . $i++; $params[] = $statu; }

    $setParts[] = "actualizado_en = NOW()";

    $params[] = $id;

    $sql = "UPDATE impuestos SET " . implode(", ", $setParts) . " WHERE id_impuesto = $" . $i . " RETURNING *";

    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        json_response(["success" => false, "message" => "Error al actualizar: " . pg_last_error($conn)], 500);
    }

    $updated = pg_fetch_assoc($result);
    json_response(["success" => true, "message" => "Impuesto actualizado correctamente", "impuesto" => $updated]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
