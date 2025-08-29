<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $res = pg_query($conn, "
        SELECT 
            r.id_rol,
            r.nombre AS rol_nombre,
            p.id_permiso,
            p.modulo,
            p.puede_ver,
            p.puede_editar,
            p.puede_borrar
        FROM public.roles r
        LEFT JOIN public.permisos p 
            ON r.id_rol = p.rol_id
        ORDER BY r.id_rol, p.modulo;
    ");
    
    if (!$res) {
        http_response_code(500);
        throw new Exception("Error en la consulta");
    }

    $rows = pg_fetch_all($res) ?? [];

    // Agrupamos por rol
    $roles = [];
    foreach ($rows as $row) {
        $idRol = $row['id_rol'];
        if (!isset($roles[$idRol])) {
            $roles[$idRol] = [
                "id_rol" => $row['id_rol'],
                "rol_nombre" => $row['rol_nombre'],
                "permisos" => []
            ];
        }
        if (!empty($row['id_permiso'])) {
            $roles[$idRol]["permisos"][] = [
                "id_permiso" => $row['id_permiso'],
                "modulo" => $row['modulo'],
                "puede_ver" => $row['puede_ver'],
                "puede_editar" => $row['puede_editar'],
                "puede_borrar" => $row['puede_borrar']
            ];
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Roles y permisos obtenidos correctamente",
        "roles" => array_values($roles) // reindexar array
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage(),
        "roles" => []
    ], JSON_UNESCAPED_UNICODE);
}
