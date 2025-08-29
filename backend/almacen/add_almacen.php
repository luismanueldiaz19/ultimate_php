<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Lista de campos obligatorios
    $requiredFields = ['nombre_almacen', 'direccion_almacen'];

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

    // Valores con trim
    $nombre = trim($data['nombre_almacen']);
    $direccion = trim($data['direccion_almacen']);
    $statu = isset($data['statu_almacen']) ? ($data['statu_almacen'] ? 't' : 'f') : 't';

    // Verificar duplicado
    $check = pg_query_params($conn, "SELECT 1 FROM almacenes WHERE nombre_almacen = $1 LIMIT 1", [$nombre]);
    if (pg_num_rows($check) > 0) {
        json_response(["success" => false, "message" => "El nombre del almacén ya existe"], 409);
    }

    // Insertar en DB
    $sql = "INSERT INTO public.almacenes (nombre_almacen, direccion_almacen, statu_almacen)
            VALUES ($1, $2, $3) 
            RETURNING id_almacen, nombre_almacen, direccion_almacen, statu_almacen, creado_en_almacen";
    $params = [$nombre, $direccion, $statu];

    $result = @pg_query_params($conn, $sql, $params);

    if (!$result) {
        $error = pg_last_error($conn);
        if (strpos($error, 'unique_nombre_almacen') !== false) {
            json_response(["success" => false, "message" => "El nombre del almacén ya existe"], 409);
        }
        json_response(["success" => false, "message" => "Error al crear almacén: " . $error], 500);
    }

    $newAlmacen = pg_fetch_assoc($result);
    json_response([
        "success" => true,
        "message" => "Almacén creado exitosamente",
        "almacen" => $newAlmacen
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>

