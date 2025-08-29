<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

header("Content-Type: application/json; charset=UTF-8");

// Leer JSON del body
$data = json_decode(file_get_contents("php://input"), true);

// Validación básica de datos obligatorios
if (empty($data['institution_name']) || empty($data['job_type'])) {
    json_response(["success" => false, "message" => "Faltan campos obligatorios: institution_name y job_type."], 400);
}

$usuarioId = $data['usuario_id'] ?? null;
if (!$usuarioId) {
    json_response(["success" => false, "message" => "Usuario no autenticado."], 401);
}

// Iniciar transacción
pg_query($conn, "BEGIN");

try {
    // Insertar un nuevo trabajo de diseño
    $sqlJob = "INSERT INTO design_jobs 
        (institution_name, job_type, version, notes, color_scheme, pantones) 
        VALUES ($1, $2, $3, $4, $5, $6)
        RETURNING design_jobs_id";

        

    $paramsJob = [
        $data['institution_name'],
    $data['job_type'],
    $data['version'] ?? 'v1',
    $data['notes'] ?? null,
    isset($data['color_scheme']) ? phpArrayToPgArray($data['color_scheme']) : null,
    isset($data['pantones']) ? phpArrayToPgArray($data['pantones']) : null
    ];

    $resultJob = pg_query_params($conn, $sqlJob, $paramsJob);

    if (!$resultJob) {
        throw new Exception("Error al guardar el trabajo de diseño.");
    }

    $job = pg_fetch_assoc($resultJob);
    $designJobId = $job['design_jobs_id'];

    // Insertar imágenes si existen
    if (!empty($data['images']) && is_array($data['images'])) {
        foreach ($data['images'] as $img) {
            if (empty($img['image_file'])) continue;

            $sqlImg = "INSERT INTO design_images 
                (design_jobs_id, image_file, location) 
                VALUES ($1, $2, $3)";
            $paramsImg = [
                $designJobId,
                $img['image_file'],
                $img['location'] ?? null
            ];

            $resultImg = pg_query_params($conn, $sqlImg, $paramsImg);
            if (!$resultImg) {
                throw new Exception("Error al guardar una de las imágenes.");
            }
        }
    }

    // Confirmar transacción
    pg_query($conn, "COMMIT");

    // Auditoría
    registrarAuditoria(
        $conn,
        $usuarioId,
        'INSERT',
        'design_jobs',
        null,
        $data
    );

    json_response([
        "success" => true,
        "message" => "Trabajo de diseño creado exitosamente",
        "design_jobs_id" => $designJobId
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
