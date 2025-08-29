<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    $requiredFields = ['nombre_categoria', 'abreviado'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missingFields[] = $field;
        }
    }
    if (!empty($missingFields)) {
        json_response(["success" => false, "message" => "Faltan campos obligatorios", "faltantes" => $missingFields], 400);
    }

    $nombre = trim($data['nombre_categoria']);
    $descripcion = trim($data['descripcion_categoria'] ?? 'N/A');
    $abreviado = trim($data['abreviado']);
    $status = isset($data['status_categoria']) ? ($data['status_categoria'] ? 't' : 'f') : 't';

    // Verificar nombre duplicado
$checkNombre = pg_query_params($conn, "SELECT 1 FROM categorias WHERE nombre_categoria = $1 LIMIT 1", [$nombre]);
if (pg_num_rows($checkNombre) > 0) {
    json_response(["success" => false, "message" => "El nombre de la categoría ya existe"], 409);
}

// Verificar abreviado duplicado
$checkAbreviado = pg_query_params($conn, "SELECT 1 FROM categorias WHERE abreviado = $1 LIMIT 1", [$abreviado]);
if (pg_num_rows($checkAbreviado) > 0) {
    json_response(["success" => false, "message" => "El abreviado ya existe"], 409);
}

    $sql = "INSERT INTO categorias (nombre_categoria, descripcion_categoria, abreviado, status_categoria)
            VALUES ($1, $2, $3, $4)
            RETURNING id_categoria, nombre_categoria, descripcion_categoria, abreviado, status_categoria, creado_en, actualizado_en";
    $params = [$nombre, $descripcion, $abreviado, $status];

    $result = @pg_query_params($conn, $sql, $params);
    
    if (!$result) {
        $error = pg_last_error($conn);
        if (strpos($error, 'unique_nombre_categoria') !== false) {
            json_response(["success" => false, "message" => "El nombre de la categoría ya existe"], 409);
        }
        json_response(["success" => false, "message" => "Error al crear categoría: " . $error], 500);
    }

    $newCategoria = pg_fetch_assoc($result);
    json_response(["success" => true, "message" => "Categoría creada exitosamente", "categoria" => $newCategoria]);

} catch (Exception $e) {
    json_response(["success" => false, "message" => "Excepción: " . $e->getMessage()], 500);
}
?>
