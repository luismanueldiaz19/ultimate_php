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
    $id_usuario         = $data['id_usuario'] ?? null;
    $nombre             = trim($data['nombre'] ?? '');
    $username           = trim($data['username'] ?? '');
    $password           = $data['password'] ?? '';
    $rol_id             = $data['rol_id'] ?? null;
    $activo             = $data['activo'] ?? true;
    $registed_by        = $data['registed_by'] ?? 'N/A';
    $depart_acceso      = $data['depart_acceso'] ?? [];

    // Validar ID
    if (empty($id_usuario)) {
        json_response([
          "success" => false,
          "message" => "Falta el id_usuario para actualizar"], 400);
    }

    // Validar campos obligatorios
    $errores = [];
    if (empty($nombre))   $errores[] = "nombre";
    if (empty($username)) $errores[] = "username";
    if (empty($password)) $errores[] = "password";

    if (!empty($errores)) {
        json_response([
            "success" => false,
            "message" => "Faltan campos obligatorios",
            "campos_faltantes" => $errores
        ], 400);
    }

    // Validación de longitud
    if (strlen($username) < 3) {
        json_response([
          "success" => false,
          "message" => "El nombre de usuario debe tener al menos 3 caracteres"
        ]);
    }
    if (strlen($password) < 6) {
        json_response([
          "success" => false,
          "message" => "La contraseña debe tener al menos 6 caracteres"
        ]);
    }

    // Hash seguro
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]);

    // Convertir arreglo a formato PostgreSQL
    $depart_acceso_pg = '{' . implode(',', array_map(fn($d) => '"' . $d . '"', $depart_acceso)) . '}';

    // Ejecutar UPDATE
    $sql = "UPDATE public.usuarios
            SET nombre = $1,
                username = $2,
                password_hash = $3,
                rol_id = $4,
                activo = $5,
                registed_by = $6,
                depart_acceso = $7
            WHERE id_usuario = $8";

    $result = pg_query_params($conn, $sql, [
        $nombre,
        $username,
        $passwordHash,
        $rol_id,
        $activo,
        $registed_by,
        $depart_acceso_pg,
        $id_usuario
    ]);

    if (!$result) {
        json_response([
          "success" => false,
          "message" => "Error al actualizar usuario"]
        );
    }

    json_response([
        "success" => true,
        "message" => "Usuario actualizado correctamente",
        "id_usuario" => $id_usuario
    ]);

} catch (Exception $e) {
    json_response(["message" => "Excepción: " . $e->getMessage()]);
}