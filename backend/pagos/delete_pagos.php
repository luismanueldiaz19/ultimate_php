<?php
include '../conexion.php';
include '../utils.php';
require '../vendor/autoload.php'; // Asegúrate de tener OTPHP instalado

use OTPHP\TOTP;

header("Content-Type: application/json; charset=UTF-8");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    // Validar campos obligatorios
    $requiredFields = ['id_usuario', 'token', 'pago_id'];
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

    $id_usuario = trim($data['id_usuario']);
    $token = trim($data['token']);
    $pago_id = intval($data['pago_id']);

    // Iniciar transacción
    pg_query($conn, "BEGIN");

    // Validar usuario master y obtener su secreto
    $res = pg_query_params($conn,
        "SELECT is_master, secret_otp FROM usuarios WHERE id_usuario = $1",
        [$id_usuario]
    );

    if (!$res || pg_num_rows($res) === 0) {
        pg_query($conn, "ROLLBACK");
        json_response(["success" => false, "message" => "Usuario no encontrado"], 404);
    }

    $row = pg_fetch_assoc($res);
    if ($row['is_master'] !== 't') {
        pg_query($conn, "ROLLBACK");
        json_response(["success" => false, "message" => "No autorizado"], 403);
    }

    // Validar token dinámico
    $totp = TOTP::create($row['secret_otp']);
    
    if (!$totp->verify($token)) {
        pg_query($conn, "ROLLBACK");
        json_response(["success" => false, "message" => "Token inválido o expirado"], 401);
    }

    // Ejecutar DELETE
    $delete = pg_query_params($conn,
        "DELETE FROM public.pagos_pre_orden WHERE pago_id = $1",
        [$pago_id]
    );

    if (!$delete) {
        pg_query($conn, "ROLLBACK");
        json_response(["success" => false, "message" => "Error al eliminar el pago"], 500);
    }

    // Confirmar transacción
    pg_query($conn, "COMMIT");

    json_response([
        "success" => true,
        "message" => "Pago eliminado correctamente",
        "pago_id" => $pago_id
    ]);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>