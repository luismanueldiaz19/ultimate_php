<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php'; // incluye la función de auditoría

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!isset($data['id_proveedor']) || !is_numeric($data['id_proveedor'])) {
        json_response(["success" => false, "message" => "El id del proveedor es obligatorio y debe ser numérico"], 400);
    }

    $id = (int)$data['id_proveedor'];
    $usuarioId = $data['id_usuario'] ?? null; // ID del usuario que realiza la actualización

    $nombre = isset($data['nombre_proveedor']) ? trim($data['nombre_proveedor']) : null;
    $contacto = $data['contacto_proveedor'] ?? null;
    $telefono = $data['telefono_proveedor'] ?? null;
    $email = $data['email_proveedor'] ?? null;
    $direccion = $data['direccion_proveedor'] ?? null;
    $statu = isset($data['statu_proveedor']) ? ($data['statu_proveedor'] ? 't' : 'f') : null;

    if ($nombre === null && $contacto === null && $telefono === null && $email === null && $direccion === null && $statu === null) {
        json_response(["success" => false, "message" => "No se enviaron campos para actualizar"], 400);
    }

    // Verificar duplicado nombre
    if ($nombre !== null) {
        $check = pg_query_params($conn, "SELECT 1 FROM proveedores WHERE nombre_proveedor = $1 AND id_proveedor <> $2 LIMIT 1", [$nombre, $id]);
        if (pg_num_rows($check) > 0) {
            json_response(["success" => false, "message" => "El nombre del proveedor ya existe"], 409);
        }
    }

    // Obtener datos anteriores para auditoría
    $resAnterior = pg_query_params($conn, "SELECT * FROM proveedores WHERE id_proveedor = $1 LIMIT 1", [$id]);
    $datos_anteriores = pg_fetch_assoc($resAnterior);

    // Construir SQL dinámico
    $setParts = [];
    $params = [];
    $i = 1;

    if ($nombre !== null) { $setParts[] = "nombre_proveedor = $" . $i++; $params[] = $nombre; }
    if ($contacto !== null) { $setParts[] = "contacto_proveedor = $" . $i++; $params[] = $contacto; }
    if ($telefono !== null) { $setParts[] = "telefono_proveedor = $" . $i++; $params[] = $telefono; }
    if ($email !== null) { $setParts[] = "email_proveedor = $" . $i++; $params[] = $email; }
    if ($direccion !== null) { $setParts[] = "direccion_proveedor = $" . $i++; $params[] = $direccion; }
    if ($statu !== null) { $setParts[] = "statu_proveedor = $" . $i++; $params[] = $statu; }

    $setParts[] = "actualizado_en_proveedor = NOW()";
    $params[] = $id;

    $sql = "UPDATE proveedores SET " . implode(", ", $setParts) . " WHERE id_proveedor = $" . $i . " RETURNING *";

    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        json_response(["success" => false, "message" => "Error al actualizar: " . pg_last_error($conn)], 500);
    }

    $updated = pg_fetch_assoc($result);

    // Registrar auditoría
    registrarAuditoria($conn, $usuarioId, 'UPDATE', 'proveedores', $datos_anteriores, $updated);

    json_response([
        "success" => true,
        "message" => "Proveedor actualizado correctamente",
        "proveedor" => $updated
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
