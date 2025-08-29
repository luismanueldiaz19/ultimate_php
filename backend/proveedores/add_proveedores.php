<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Campos obligatorios
    $requiredFields = ['nombre_proveedor','id_usuario','telefono_proveedor','direccion_proveedor'];

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

    $nombre = trim($data['nombre_proveedor']);
    $contacto = trim($data['contacto_proveedor'] ?? '');
    $telefono = trim($data['telefono_proveedor'] ?? '');
    $email = trim($data['email_proveedor'] ?? '');
    $direccion = trim($data['direccion_proveedor'] ?? '');
    $id_usuario = trim($data['id_usuario'] ?? '');
    $statu = isset($data['statu_proveedor']) ? ($data['statu_proveedor'] ? 't' : 'f') : 't';

    // Verificar duplicado
    $check = pg_query_params($conn, "SELECT 1 FROM proveedores WHERE nombre_proveedor = $1 LIMIT 1", [$nombre]);
    if (pg_num_rows($check) > 0) {
        json_response(["success" => false, "message" => "El proveedor ya existe"], 409);
    }

    // Insertar
    $sql = "INSERT INTO proveedores 
            (nombre_proveedor, contacto_proveedor, telefono_proveedor, email_proveedor, direccion_proveedor, statu_proveedor,id_usuario)
            VALUES ($1, $2, $3, $4, $5, $6,$7)
            RETURNING id_proveedor, nombre_proveedor, contacto_proveedor, telefono_proveedor, email_proveedor, direccion_proveedor, statu_proveedor, creado_en_proveedor, id_usuario";

    $params = [$nombre, $contacto, $telefono, $email, $direccion, $statu, $id_usuario];
    $result = @pg_query_params($conn, $sql, $params);

    if (!$result) {
        $error = pg_last_error($conn);
        json_response(["success" => false, "message" => "Error al crear proveedor: " . $error], 500);
    }

    $newProveedor = pg_fetch_assoc($result);
    json_response(["success" => true, "message" => "Proveedor creado exitosamente", "proveedor" => $newProveedor]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
