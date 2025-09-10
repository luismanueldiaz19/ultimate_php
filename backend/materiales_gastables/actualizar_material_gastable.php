<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Validar ID
    if (!isset($data['materiales_gastable_id']) || !is_numeric($data['materiales_gastable_id'])) {
        json_response(["success" => false, "message" => "ID de material requerido o inválido"]);
    }

    // Campos obligatorios
    $requiredFields = ['id_cuenta', 'nombre_materia', 'unidad', 'costo', 'stock_actual', 'ubicacion'];
    $missingFields = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        json_response([
            "success" => false,
            "message" => "Faltan campos obligatorios",
            "faltantes" => $missingFields
        ]);
    }

    // Sanitizar valores
    $id = (int) $data['materiales_gastable_id'];
    $id_cuenta = (int) $data['id_cuenta'];
    $nombre = trim($data['nombre_materia']);
    $unidad = trim($data['unidad']);
    $costo = (float) $data['costo'];
    $stock = (float) $data['stock_actual'];
    $ubicacion = trim($data['ubicacion']);

    // Actualizar en DB
    $sql = "UPDATE materiales_gastable
            SET id_cuenta = $1, nombre_materia = $2, unidad = $3, costo = $4, stock_actual = $5, ubicacion = $6
            WHERE materiales_gastable_id = $7
            RETURNING materiales_gastable_id, nombre_materia, unidad, costo, stock_actual, ubicacion";

    $params = [$id_cuenta, $nombre, $unidad, $costo, $stock, $ubicacion, $id];
    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al actualizar material: " . $error]);
    }

    $updated = pg_fetch_assoc($result);
    json_response([
        "success" => true,
        "message" => "Material actualizado exitosamente",
        "material" => $updated
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()]);
}
?>