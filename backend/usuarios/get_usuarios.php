<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $res = pg_query($conn, "
        SELECT 
            u.id_usuario,
            u.nombre AS usuario_nombre,
            u.username,
            u.activo,
            u.creado_en,
            r.id_rol,
            r.nombre AS rol_nombre,
            p.id_permiso,
            p.modulo,
            p.puede_ver,
            p.puede_editar,
            p.puede_borrar
        FROM public.usuarios u
        JOIN public.roles r 
            ON u.rol_id = r.id_rol
        LEFT JOIN public.permisos p 
            ON r.id_rol = p.rol_id
        ORDER BY u.id_usuario, r.id_rol, p.modulo;
    ");
    
    if (!$res) {
        throw new Exception("Error en la consulta");
    }

    $rows = pg_fetch_all($res) ?? [];

    // Agrupar usuarios y permisos
    $usuarios = [];
    foreach ($rows as $row) {
        $idUsuario = $row['id_usuario'];
        if (!isset($usuarios[$idUsuario])) {
            $usuarios[$idUsuario] = [
                "id_usuario" => $row['id_usuario'],
                "usuario_nombre" => $row['usuario_nombre'],
                "username" => $row['username'],
                "activo" => $row['activo'],
                "creado_en" => $row['creado_en'],
                "rol" => [
                    "id_rol" => $row['id_rol'],
                    "rol_nombre" => $row['rol_nombre'],
                ],
                "permisos" => []
            ];
        }

        if (!empty($row['id_permiso'])) {
            $usuarios[$idUsuario]['permisos'][] = [
                "id_permiso" => $row['id_permiso'],
                "modulo" => $row['modulo'],
                "puede_ver" => $row['puede_ver'],
                "puede_editar" => $row['puede_editar'],
                "puede_borrar" => $row['puede_borrar'],
            ];
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Usuarios obtenidos correctamente",
        "usuarios" => array_values($usuarios) // reindexar array
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "usuarios" => []
    ], JSON_UNESCAPED_UNICODE);
}
