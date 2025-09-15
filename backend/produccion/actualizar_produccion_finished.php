<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

$data = json_decode(file_get_contents("php://input"), true); // ✅ true convierte a array asociativo

if (!is_array($data)) {
    json_response([
        "success" => false,
        "message" => "Formato de datos inválido. Se esperaba un objeto JSON plano."
    ], 400);
    exit;
}

pg_query($conn, "BEGIN");

$errores = [];
$exitos = [];

foreach ($data as $campo_id => $cant) {
    if (!is_numeric($campo_id) || !is_numeric($cant)) {
        $errores[] = "Valores inválidos: campo_id=$campo_id, cant=$cant";
        continue;
    }

    $sql = "UPDATE public.hoja_produccion_campos SET cant = $1 WHERE hoja_produccion_campos_id = $2";
    $res = pg_query_params($conn, $sql, [$cant, $campo_id]);

    if (!$res) {
        $errores[] = "Error actualizando campo_id=$campo_id";
    } else {
        $exitos[] = "Actualizado campo_id=$campo_id con cant=$cant";
    }
}

if (count($errores) > 0) {
    pg_query($conn, "ROLLBACK");
    json_response([
        "success" => false,
        "message" => "Errores al actualizar campos",
        "errores" => $errores,
        "exitos" => $exitos
    ]);
    exit;
}

pg_query($conn, "COMMIT");
json_response([
    "success" => true,
    "message" => "Todos los campos actualizados correctamente",
    "exitos" => $exitos
]);