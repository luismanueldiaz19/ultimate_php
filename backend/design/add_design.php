<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");

$data = json_decode(file_get_contents("php://input"), true);

// Validación básica
if (empty($data['institution_name'])) {
    json_response(["success" => false, "message" => "Falta institution_name"], 400);
}

$usuarioId = $data['usuario_id'] ?? null;
if (!$usuarioId) {
    json_response(["success" => false, "message" => "Usuario no autenticado"], 401);
}

pg_query($conn, "BEGIN");

try {
    $sqlJob = "INSERT INTO design_jobs 
        (institution_name, version, notes, color_scheme, pantones) 
        VALUES ($1, $2, $3, $4, $5)
        RETURNING *";

    $paramsJob = [
        $data['institution_name'],
        $data['version'] ?? 'v1',
        $data['notes'] ?? null,
        isset($data['color_scheme']) ? phpArrayToPgArray($data['color_scheme']) : null,
        isset($data['pantones']) ? phpArrayToPgArray($data['pantones']) : null
    ];

    $resultJob = @pg_query_params($conn, $sqlJob, $paramsJob);

    if (!$resultJob) {
        json_response([
              "success" => false,
              "message" => "Existe Este Nombre Institucion/logo/clientes",
           ]);
         exit;
        // throw new Exception("Error al insertar en design_jobs.");
    }

    $job = pg_fetch_assoc($resultJob);
    
    pg_query($conn, "COMMIT");

    json_response([
        "success" => true,
        "message" => "Registro primario correctamente",
        "data" => $job
    ]);

} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    json_response(["success" => false, "message" => $e->getMessage()], 500);
}

function phpArrayToPgArray($array) {
    if (empty($array)) return null;
    return '{' . implode(',', array_map(function($v) {
        return '"' . addslashes($v) . '"';
    }, $array)) . '}';
}