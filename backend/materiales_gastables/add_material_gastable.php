<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

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
        ], 400);
    }

    // Sanitizar valores
    $id_cuenta = (int) $data['id_cuenta'];
    $nombre = trim($data['nombre_materia']);
    $unidad = trim($data['unidad']);
    $costo = (float) $data['costo'];
    $stock = (float) $data['stock_actual'];
    $ubicacion = trim($data['ubicacion']);

    // Verificar duplicado por nombre
    $check = pg_query_params($conn, "SELECT 1 FROM materiales_gastable WHERE nombre_materia = $1 LIMIT 1", [$nombre]);
    if (pg_num_rows($check) > 0) {
        json_response(["success" => false, "message" => "El material ya existe"], 409);
    }

    // Insertar en DB
    $sql = "INSERT INTO materiales_gastable (id_cuenta, nombre_materia, unidad, costo, stock_actual, ubicacion)
            VALUES ($1, $2, $3, $4, $5, $6)
            RETURNING materiales_gastable_id, nombre_materia, unidad, costo, stock_actual, ubicacion";
    $params = [$id_cuenta, $nombre, $unidad, $costo, $stock, $ubicacion];

    $result = @pg_query_params($conn, $sql, $params);

    if (!$result) {
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al crear material: " . $error], 500);
    }

    $newMaterial = pg_fetch_assoc($result);
    json_response([
        "success" => true,
        "message" => "Material creado exitosamente",
        "material" => $newMaterial
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>