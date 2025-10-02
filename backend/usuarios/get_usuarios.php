<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $res = pg_query($conn, "SELECT m.id_usuario, m.nombre, m.username,m.activo,
m.creado_en, m.registed_by, m.depart_acceso, m.is_master,ma.modulo_id,
pmu.modulo_action_id, mn.modulo_name, ma.name_action
FROM public.usuarios as m
left join public.permission_modulo_users pmu on pmu.id_usuario = m.id_usuario 
left join public.modulo_action ma on ma.modulo_action_id = pmu.modulo_action_id
left join public.modulo mn on mn.modulo_id = ma.modulo_id
where m.is_master = false
order by m.nombre ASC ");
    
    if (!$res) {
        throw new Exception("Error en la consulta");
    }

    $rows = pg_fetch_all($res) ?? [];

    // Agrupar usuarios y permisos
    $usuarios = [];
    foreach ($rows as $row) {
        $idUsuario = $row['id_usuario'];
        if (!isset($usuarios[$idUsuario])) {

            $departAcceso = $row['depart_acceso'];

               if (is_string($departAcceso)) {
                  // Convierte '{ventas,compras}' → ['ventas', 'compras']
                 $departAcceso = array_filter(explode(',', trim($departAcceso, '{}')));
              }

            $usuarios[$idUsuario] = [
                "id_usuario" => $row['id_usuario'],
                "usuario_nombre" => $row['nombre'],
                "username" => $row['username'],
                "activo" => $row['activo'],
                "creado_en" => $row['creado_en'],
                "registed_by" => $row['registed_by'],
                "depart_acceso" => $departAcceso,
                "is_master" => $row['is_master'] == 't' ? true :  false,
                "modulos" => []
            ];
        }

        if (!empty($row['modulo_action_id'])) {
            $usuarios[$idUsuario]['modulos'][] = [
                "modulo_id" => $row['modulo_id'],
                "modulo_action_id" => $row['modulo_action_id'],
                "modulo_name" => $row['modulo_name'],
                "name_action" => $row['name_action']
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
