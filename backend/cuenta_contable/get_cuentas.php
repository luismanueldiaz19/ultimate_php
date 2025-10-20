<?php
include '../conexion.php';
include '../utils.php';


try {
    $res = pg_query($conn, "SELECT m.codigo, m.nombre, m.nivel,tipo_cuenta.descricion as tipo,
tipo_cuenta.status_tipo,m.padre, m.tipo_cuenta_id
FROM public.catalogo_cuentas as m
inner join public.tipo_cuenta  on tipo_cuenta.tipo_cuenta_id = m.tipo_cuenta_id
order by m.codigo ASC");

    if (!$res) {
        throw new Exception("Error en la consulta: " . pg_last_error($conn));
    }

    $data = pg_fetch_all($res) ?? [];

    echo json_encode([
        "success" => true,
        "message" => "cuentas obtenidos correctamente",
        "cuentas" => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage(),
        "cuentas" => []
    ]);
}
?>
