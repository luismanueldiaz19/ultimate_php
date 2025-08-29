<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // id_almacen obligatorio
    if (!isset($data['id_almacen']) || trim($data['id_almacen']) === '') {
        json_response(["success" => false, "message" => "Falta el id del almacén"], 400);
    }
    $id = (int)$data['id_almacen'];

    // Lista de campos obligatorios
    $requiredFields = ['nombre_almacen', 'direccion_almacen' , 'id_almacen'];

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

    // Verificar duplicado (exceptuando el registro actual)
    $check = pg_query_params(
        $conn,
        "SELECT 1 FROM almacenes WHERE nombre_almacen = $1 AND id_almacen <> $2 LIMIT 1",
        [$nombre, $id]
    );
    if (pg_num_rows($check) > 0) {
        json_response(["success" => false, "message" => "El nombre del almacén ya existe"], 409);
    }

    // Actualizar en DB
    $sql = "UPDATE public.almacenes 
            SET nombre_almacen = $1, direccion_almacen = $2, statu_almacen = $3, actualizado_en_almacen = NOW()
            WHERE id_almacen = $4
            RETURNING id_almacen, nombre_almacen, direccion_almacen, statu_almacen, actualizado_en_almacen";
    $params = [$nombre, $direccion, $statu, $id];

    $result = @pg_query_params($conn, $sql, $params);

    if (!$result) {
        $error = pg_last_error($conn);
        if (strpos($error, 'unique_nombre_almacen') !== false) {
            json_response(["success" => false, "message" => "El nombre del almacén ya existe"], 409);
        }
        json_response(["success" => false, "message" => "Error al actualizar almacén: " . $error], 500);
    }

    $updatedAlmacen = pg_fetch_assoc($result);
    json_response([
        "success" => true,
        "message" => "Almacén actualizado exitosamente",
        "almacen" => $updatedAlmacen
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
