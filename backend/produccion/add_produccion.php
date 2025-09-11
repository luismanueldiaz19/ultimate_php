<?php
include '../conexion.php';
include '../utils.php';


// 'start_date': DateTime.now().toString().substring(0, 19),
//       'usuario_id': currentUsuario!.idUsuario,
//       'ficha_id': widget.orden?.fichaId,
//       'planificacion_work_id': widget.orden!.planificacionWorkId,
//       'type_work_id': tipoPicked!.typeWorkId,
//       'num_orden': widget.orden?.numOrden,
//       'campos': tipoPicked!.campos
//           ?.map((value) => {'campo_id': value.camposTypeWorkId})
//           .toList()

$data = json_decode(file_get_contents('php://input'), true);

// Validaciones iniciales
$start_date = $data['start_date'] ?? null;
$usuario_id = $data['usuario_id'] ?? null;
$ficha_id = $data['ficha_id'] ?? null;
$planificacion_work_id = $data['planificacion_work_id'] ?? null;
$type_work_id = $data['type_work_id'] ?? null;
$num_orden = $data['num_orden'] ?? null;
$item_pre_orden_id = $data['item_pre_orden_id'] ?? null;
$campos = $data['campos'] ?? [];

//num_orden, ficha_id

// //INSERT INTO public.hoja_produccion(
// 	hoja_produccion_id, start_date, end_date, created_at, estado_hoja_producion, usuario_id, planificacion_work_id, comentario_producion)
// 	VALUES (?, ?, ?, ?, ?, ?, ?, ?);

if (!$start_date || !$usuario_id || !$planificacion_work_id || !$type_work_id || !$item_pre_orden_id || !$num_orden || !$ficha_id || !is_array($campos)) {
    json_response(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

pg_query($conn, "BEGIN");

// Insertar hoja_produccion
$sql_hoja = "INSERT INTO public.hoja_produccion(start_date, usuario_id, planificacion_work_id, type_work_id,num_orden, ficha_id, estado_hoja_producion)
             VALUES ($1, $2, $3, $4, $5, $6, $7) RETURNING hoja_produccion_id";

$res_hoja = pg_query_params($conn, $sql_hoja, [$start_date, $usuario_id, $planificacion_work_id, $type_work_id , $num_orden, $ficha_id,'EN PRODUCCION']);

if (!$res_hoja) {
    pg_query($conn, "ROLLBACK");
    json_response(['success' => false, 'message' => 'Error insertando hoja_produccion']);
    exit;
}

$hoja_id = pg_fetch_result($res_hoja, 0, 'hoja_produccion_id');

// ✅ Actualizar estado_produccion en planificacion_work
$sql_update_orden = "UPDATE public.planificacion_work SET estado_planificacion_work = $1 WHERE planificacion_work_id = $2";



$res_update = pg_query_params($conn, $sql_update_orden, ['EN PRODUCCION', $planificacion_work_id]);

if (!$res_update) {
    pg_query($conn, "ROLLBACK");
    json_response(['success' => false, 'message' => 'Error actualizando estado de orden_items']);
    exit;
}

// ✅ Actualizar estado_general en list_ordenes usando orden_items_id
$sql_list_ordenes = "UPDATE public.item_pre_orden SET estado_item= $1 WHERE item_pre_orden_id = $2";

$res_list_ordenes = pg_query_params($conn, $sql_list_ordenes, ['EN PRODUCCION', $item_pre_orden_id]);

if (!$res_list_ordenes) {
    pg_query($conn, "ROLLBACK");
    json_response(['success' => false, 'message' => 'Error actualizando estado en list_ordenes']);
    exit;
}

$errores = [];
$mensajes = [];


//INSERT INTO public.hoja_produccion_campos(hoja_produccion_id, campos_type_work_id, cant)VALUES ();
$sql_campo = "INSERT INTO public.hoja_produccion_campos(hoja_produccion_id, campos_type_work_id, cant)
              VALUES ($1, $2, $3)";

foreach ($campos as $campo) {
    $campo_id = $campo['campo_id'] ?? null;
    $cant = isset($campo['cant']) ? intval($campo['cant']) : 0;

    if (!$campo_id) continue;

    $res_campo = pg_query_params($conn, $sql_campo, [$hoja_id, $campo_id, $cant]);

    if (!$res_campo) {
        $errores[] = "Error al insertar campo con ID: $campo_id";
    } else {
        $mensajes[] = "Campo ID $campo_id insertado correctamente"; 
    }
}

// Finalizar transacción
if (count($errores) > 0) {
    pg_query($conn, "ROLLBACK");
    json_response([
        'success' => false,
        'hoja_produccion_id' => $hoja_id,
        'message' => 'Errores al insertar campos',
        'errores' => $errores,
        'mensajes_exitosos' => $mensajes
    ]);
    exit;
}

pg_query($conn, "COMMIT");
json_response([
    'success' => true,
    'hoja_produccion_id' => $hoja_id,
    'message' => 'Hoja insertada correctamente',
    'mensajes' => $mensajes
]);
