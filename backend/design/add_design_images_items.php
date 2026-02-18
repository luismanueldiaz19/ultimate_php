<?php

require 'cargar_imagen.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_FILES['image'])) {
    echo json_encode(['status' => false, 'mensaje' => 'No se recibió ninguna imagen'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar campos requeridos y que no estén vacíos
$campos    = ['design_tipo_id','comment_imagen', 'body_ubicacion','registed_by'];
$faltantes = [];

foreach ($campos as $campo) {
    if (!isset($_POST[$campo]) || trim($_POST[$campo]) === '') {
        $faltantes[] = $campo;
    }
}

if (!empty($faltantes)) {
    echo json_encode([
        'status'    => false,
        'message'   => 'Faltan campos requeridos o están vacíos',
        'faltantes' => $faltantes
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Llamar la función con todos los argumentos
$resultado = subirArchivo(
    $_FILES['image'],
    $_POST['design_tipo_id'],
    $_POST['comment_imagen'],
    $_POST['body_ubicacion'],
    $_POST['registed_by'],
);

// Evaluar si fue exitoso
if ($resultado['success']) {
    echo json_encode([
        'status'  => true,
        'message' => 'Imagen subida correctamente',
        'body'    => $resultado
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'status'  => false,
        'message' => 'Error al subir imagen',
        'body'    => $resultado
    ], JSON_UNESCAPED_UNICODE);
}
