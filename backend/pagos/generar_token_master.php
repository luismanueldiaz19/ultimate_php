<?php
include '../conexion.php';
require '../vendor/autoload.php'; // Asegúrate de tener OTPHP instalado
include '../utils.php'; // ← Esta línea es la que falta




use OTPHP\TOTP;

header("Content-Type: application/json; charset=UTF-8");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    if (!isset($data['id_usuario']) || trim($data['id_usuario']) === '') {
        json_response(["success" => false, "message" => "Falta el id_usuario"], 400);
    }

    $id_usuario = trim($data['id_usuario']);

    // Verificar si el usuario es master
    $res = pg_query_params($conn,
        "SELECT nombre, is_master FROM usuarios WHERE id_usuario = $1",
        [$id_usuario]
    );

    if (!$res || pg_num_rows($res) === 0) {
        json_response(["success" => false, "message" => "Usuario no encontrado"], 404);
    }

    $row = pg_fetch_assoc($res);
    if ($row['is_master'] !== 't') {
        json_response(["success" => false, "message" => "Usuario no autorizado"], 403);
    }

    // Generar secreto OTP
    $totp = TOTP::create();
    $secret = $totp->getSecret();

    // Guardar en la base de datos
    $update = pg_query_params($conn,
        "UPDATE usuarios SET secret_otp = $1 WHERE id_usuario = $2",
        [$secret, $id_usuario]
    );

    if (!$update) {
        json_response(["success" => false, "message" => "Error al guardar el secreto OTP"], 500);
    }

    // Preparar QR para escanear
    $totp->setLabel($row['nombre']);
    $totp->setIssuer('Sistema Empresarial');
    $uri = $totp->getProvisioningUri();
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($uri) . "&size=200x200";

    json_response([
        "success" => true,
        "message" => "Secreto OTP generado exitosamente",
        "secret_otp" => $secret,
        "qr_url" => $qr_url
    ]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>