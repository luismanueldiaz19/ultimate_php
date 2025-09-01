<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $sql = "SELECT 
  pc.productos_catalogos_id,
  pc.nombre_catalogo,
  pc.descripcion_catalogo,
  pc.ruta_imagen,
  pc.precio,
  pc.activo,
  pc.creado_en,
  c.id_categoria,
  c.nombre_categoria,
  c.descripcion_categoria,
  c.abreviado,
  c.secuencia,
  c.status_categoria,
  c.creado_en AS categoria_creado_en,
  c.actualizado_en
FROM productos_catalogos pc
INNER JOIN categorias c ON pc.id_categoria = c.id_categoria
ORDER BY c.nombre_categoria ASC";
    $result = pg_query($conn, $sql);

    if (!$result) {
        json_response(["success" => false, "message" => "Error al obtener productos: " . pg_last_error($conn)], 500);
    }

    $productos = pg_fetch_all($result) ?: [];
    json_response(["success" => true, "productos" => $productos]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
