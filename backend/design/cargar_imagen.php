<?php

include '../conexion.php';
include '../utils.php';
function subirArchivo(
    $file,
    $design_tipo_id,
    $comment_imagen,
    $body_ubicacion,
    $registed_by,
    $directorio = 'cargas_pruebas/'
) {
    global $conn;

    // Validar archivo
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Archivo no válido'];
    }

    $extPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $extension     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $extPermitidas)) {
        return ['success' => false, 'error' => 'Extensión no permitida'];
    }

    if (!is_dir($directorio)) {
        mkdir($directorio, 0777, true);
    }

    $nombreOriginal   = pathinfo($file['name'], PATHINFO_FILENAME);
    $timestamp        = date('YmdHis');
    $nombreModificado = $nombreOriginal . '_' . $timestamp . '.' . $extension;
    $rutaFinal        = $directorio . $nombreModificado;

    // Iniciar transacción
    pg_query($conn, 'BEGIN');

    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $rutaFinal)) {
        pg_query($conn, 'ROLLBACK');
        return ['success' => false, 'error' => 'Error al mover el archivo'];
    }

    $mime   = mime_content_type($rutaFinal);
    $tamano = filesize($rutaFinal);

    $sql = 'INSERT INTO public.design_images_items(design_tipo_id, comment_imagen, body_ubicacion, ruta,registed_by) VALUES (
        $1, $2, $3, $4,$5) RETURNING design_images_items_id';

    $params = [
        $design_tipo_id,
        $comment_imagen,
        $body_ubicacion,
        $rutaFinal,
        $registed_by
    ];

    $stmt = @pg_query_params($conn, $sql, $params);

    if (!$stmt) {
        // Eliminar archivo si ya fue movido
        if (file_exists($rutaFinal)) {
            unlink($rutaFinal);
        }
        pg_query($conn, 'ROLLBACK');
        return ['success' => false, 'error' => 'Error en la base de datos'];
    }

    pg_query($conn, 'COMMIT');

    $result      = pg_fetch_assoc($stmt);
    $idInsertado = $result['design_images_items_id'] ?? null;

    return [
        'success'                => true,
        'design_images_items_id' => $idInsertado,
        'nombre_modificado'      => $nombreModificado,
        'ruta'                   => $rutaFinal,
        'mime'                   => $mime,
        'tamano'                 => $tamano,
        'registed_by'            => $registed_by,
    ];
}
