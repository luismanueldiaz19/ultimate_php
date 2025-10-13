<?php
include '../conexion.php';
include '../utils.php';
include '../auditoria/auditoria_log.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Método no permitido'], 405);
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

$usuarioId = $data['usuario_id'] ?? null;
$preOrdenId = $data['pre_orden_id'] ?? null;
$tipoNcf = $data['tipo_ncf'] ?? 'B02';

if (!$usuarioId || !$preOrdenId) {
    json_response(["success" => false, "message" => "Faltan datos obligatorios"]);
}

pg_query($conn, "BEGIN");

try {
    // PASO 1: Verificar si ya tiene comprobante
    $resCheck = pg_query_params($conn, "SELECT num_comprobante FROM pre_orden WHERE pre_orden_id = $1 FOR UPDATE", [$preOrdenId]);
    $rowCheck = pg_fetch_assoc($resCheck);

    if ($rowCheck['num_comprobante']) {
         json_response([
        "success" => false,
        "message" => "La orden ya tiene un comprobante asignado: " . $rowCheck['num_comprobante'] ]);
    }

    // PASO 2: Obtener y bloquear secuencia NCF
    $resNCF = pg_query_params($conn, "SELECT * FROM secuencias_ncf WHERE tipo_ncf = $1 FOR UPDATE", [$tipoNcf]);
    $ncfData = pg_fetch_assoc($resNCF);

    if (!$ncfData) {
         json_response([
        "success" => false,
        "message" => "No existe secuencia para tipo NCF '$tipoNcf'"]);
    }

    $secuencia = str_pad($ncfData['secuencia_actual'], 8, "0", STR_PAD_LEFT);
    $ncf = $ncfData['prefijo'] . $secuencia;

    // PASO 3: Asignar comprobante a la orden
    pg_query_params($conn, "
        UPDATE pre_orden
        SET num_comprobante = $1,
            fecha_emision = NOW(),
            is_facturado = true
        WHERE pre_orden_id = $2
    ", [$ncf, $preOrdenId]);

    // PASO 4: Incrementar secuencia
    pg_query_params($conn, "
        UPDATE secuencias_ncf
        SET secuencia_actual = secuencia_actual + 1
        WHERE tipo_ncf = $1
    ", [$tipoNcf]);

  
     // PASO 5: Auditoría
        registrarAuditoria($conn, $usuarioId, 'EMITIR_COMPROBANTE', 'pre_orden', null, ["pre_orden_id" => $preOrdenId,"num_comprobante" => $ncf], $preOrdenId);

        pg_query($conn, "COMMIT");

       json_response([
           "success" => true,
           "message" => "Comprobante emitido exitosamente",
           "num_comprobante" => $ncf
          ]);

    } catch (Exception $e) {
         pg_query($conn, "ROLLBACK");
         json_response(["success" => false, "message" => $e->getMessage()], 500);
      }