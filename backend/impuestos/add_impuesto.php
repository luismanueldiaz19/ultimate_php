<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Campos obligatorios
    $requiredFields = ['nombre_impuesto', 'porcentaje_impuesto'];
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
    $nombre = trim($data['nombre_impuesto']);
    $porcentaje = $data['porcentaje_impuesto'];
    $statu = isset($data['statu_impuesto']) ? ($data['statu_impuesto'] ? 't' : 'f') : 't';

    // Verificar duplicado por nombre
    $check = pg_query_params($conn, "SELECT 1 FROM impuestos WHERE nombre_impuesto = $1 LIMIT 1", [$nombre]);
    if (pg_num_rows($check) > 0) {
        json_response(["success" => false, "message" => "El nombre del impuesto ya existe"], 409);
    }

    // Insertar en DB
    $sql = "INSERT INTO public.impuestos (nombre_impuesto, porcentaje_impuesto, statu_impuesto)
            VALUES ($1, $2, $3) 
            RETURNING id_impuesto, nombre_impuesto, porcentaje_impuesto, statu_impuesto, creado_en, actualizado_en";
    $params = [$nombre, $porcentaje, $statu];

    $result = @pg_query_params($conn, $sql, $params);

    if (!$result) {
        $error = pg_last_error($conn);
        if (strpos($error, 'impuestos_pkey') !== false || strpos($error, 'nombre_impuesto') !== false) {
            json_response(["success" => false, "message" => "El nombre del impuesto ya existe"], 409);
        }
        json_response(["success" => false, "message" => "Error al crear impuesto: " . $error], 500);
    }

    $newImpuesto = pg_fetch_assoc($result);
    json_response([
        "success" => true,
        "message" => "Impuesto creado exitosamente",
        "impuesto" => $newImpuesto
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
