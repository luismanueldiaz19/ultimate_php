<?php
include '../conexion.php';
include '../utils.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        json_response([
            "success" => false,
            "message" => "Entrada inválida, se esperaba JSON"
        ]);
    }

    // Extraer y validar campos
    $id_usuario    = $data['id_usuario'] ?? null;
    $nombre        = trim($data['usuario_nombre'] ?? '');
    $username      = trim($data['username'] ?? '');
    $depart_acceso = $data['depart_acceso'] ?? [];

    // Validar ID y campos obligatorios
    $errores = [];
    if (empty($id_usuario))   $errores[] = "id_usuario";
    if (empty($nombre))       $errores[] = "usuario_nombre";
    if (empty($username))     $errores[] = "username";

    if (!empty($errores)) {
        json_response([
            "success" => false,
            "message" => "Faltan campos obligatorios",
            "campos_faltantes" => $errores
        ], 400);
    }

    // Convertir arreglo a formato PostgreSQL
    $depart_acceso_pg = '{' . implode(',', array_map(fn($d) => '"' . $d . '"', $depart_acceso)) . '}';

    // Ejecutar UPDATE
    $sql = "UPDATE public.usuarios
            SET nombre = $1,
                username = $2,
                depart_acceso = $3
            WHERE id_usuario = $4";

    $result = pg_query_params($conn, $sql, [
        $nombre,
        $username,
        $depart_acceso_pg,
        $id_usuario
    ]);

    if (!$result) {
        json_response([
            "success" => false,
            "message" => "Error al actualizar usuario"
        ]);
    }

    json_response([
        "success" => true,
        "message" => "Usuario actualizado correctamente",
        "id_usuario" => $id_usuario
    ]);

} catch (Exception $e) {
    json_response(["message" => "Excepción: " . $e->getMessage()]);
}