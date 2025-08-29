<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Campos obligatorios
    $requiredFields = ['nombre_medida'];

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

    // Valores
    $nombre = trim($data['nombre_medida']);
    $abreviatura = trim($data['abreviatura_medida'] ?? '');
    $statu = isset($data['statu_medida']) ? ($data['statu_medida'] ? 't' : 'f') : 't';

    // Verificar duplicado por nombre
    $check = pg_query_params($conn, "SELECT 1 FROM unidades_medida WHERE nombre_medida = $1 LIMIT 1", [$nombre]);
    if (pg_num_rows($check) > 0) {
        json_response(["success" => false, "message" => "La unidad de medida ya existe"], 409);
    }

    // Insertar en DB
    $sql = "INSERT INTO unidades_medida (nombre_medida, abreviatura_medida, statu_medida)
            VALUES ($1, $2, $3)
            RETURNING id_unidad, nombre_medida, abreviatura_medida, statu_medida, creado_en_medida";
    $params = [$nombre, $abreviatura, $statu];

    $result = @pg_query_params($conn, $sql, $params);

    if (!$result) {
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al crear unidad: " . $error], 500);
    }

    $newUnidad = pg_fetch_assoc($result);
    json_response([
        "success" => true,
        "message" => "Unidad de medida creada exitosamente",
        "unidad" => $newUnidad
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
