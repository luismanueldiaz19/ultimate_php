<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $input = json_decode(file_get_contents("php://input"), true);

    $limit  = isset($input['limit']) ? (int)$input['limit'] : 20;
    $offset = isset($input['offset']) ? (int)$input['offset'] : 0;
    $filtro = isset($input['filtro']) ? trim($input['filtro']) : "";

    if ($filtro !== "") {
        // Caso con filtro
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
        WHERE c.nombre_categoria ILIKE $1
        ORDER BY c.nombre_categoria ASC
        LIMIT $2::int OFFSET $3::int";

        $params = ["%$filtro%", $limit, $offset];
    } else {
        // Caso sin filtro
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
        ORDER BY c.nombre_categoria ASC
        LIMIT $1::int OFFSET $2::int";

        $params = [$limit, $offset];
    }

    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        json_response(["success" => false, "message" => "Error al obtener productos: " . pg_last_error($conn)], 500);
    }

    $productos = pg_fetch_all($result) ?: [];
    json_response(["success" => true, "productos" => $productos]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>