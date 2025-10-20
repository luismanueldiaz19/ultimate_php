<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../../connection.php';
    header("Access-Control-Allow-Origin: *");
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    // Validar campos requeridos
    $required = ['fecha', 'descripcion', 'monto_total', 'operacion'];
    $missing = [];
    foreach ($required as $r) {
        if (empty($input[$r])) $missing[] = $r;
    }

    if (!empty($missing)) {
        echo json_encode([
            'success' => false,
            'message' => 'Campos obligatorios faltantes.',
            'fields' => $missing
        ]);
        exit;
    }

    // Iniciar transacción
    pg_query($connection, "BEGIN");

    try {
        // 🔹 1️⃣ Crear el asiento contable principal
        $insert_asiento = "
            INSERT INTO asientos_contables (fecha, descripcion, total, estado)
            VALUES ($1, $2, $3, 'Registrado')
            RETURNING id_asiento
        ";
        $params_asiento = [
            $input['fecha'],
            $input['descripcion'],
            $input['monto_total']
        ];
        $res_asiento = pg_query_params($connection, $insert_asiento, $params_asiento);
        if (!$res_asiento) throw new Exception(pg_last_error($connection));

        $row_asiento = pg_fetch_assoc($res_asiento);
        $id_asiento = $row_asiento['id_asiento'];

        // 🔹 2️⃣ Buscar las cuentas configuradas para la operación
        $query_param = "SELECT codigo_cuenta, descripcion FROM parametros_contables WHERE nombre_operacion = $1";
        $res_param = pg_query_params($connection, $query_param, [$input['operacion']]);
        if (!$res_param) throw new Exception("Error al obtener parámetros contables.");

        if (pg_num_rows($res_param) == 0) {
            throw new Exception("No existe configuración contable para la operación: " . $input['operacion']);
        }

        // 🔹 3️⃣ Insertar detalles del asiento
        // Se asume que la operación define al menos 2 cuentas: una de gasto (debe) y una de pago (haber)
        $monto_total = floatval($input['monto_total']);
        $detalles_insertados = 0;

        while ($row = pg_fetch_assoc($res_param)) {
            $codigo_cuenta = $row['codigo_cuenta'];
            $descripcion = $row['descripcion'];

            // Regla simple: si en la descripción aparece "gasto" -> DEBE, si aparece "pago" o "caja" -> HABER
            $debe = 0;
            $haber = 0;

            if (stripos($descripcion, 'gasto') !== false || stripos($descripcion, 'consumo') !== false) {
                $debe = $monto_total;
            } else {
                $haber = $monto_total;
            }

            $insert_detalle = "
                INSERT INTO detalle_asiento (id_asiento, codigo_cuenta, debe, haber)
                VALUES ($1, $2, $3, $4)
            ";
            $params_detalle = [$id_asiento, $codigo_cuenta, $debe, $haber];
            $res_det = pg_query_params($connection, $insert_detalle, $params_detalle);
            if (!$res_det) throw new Exception(pg_last_error($connection));

            $detalles_insertados++;
        }

        if ($detalles_insertados < 2) {
            throw new Exception("La operación debe tener al menos dos cuentas configuradas (debe y haber).");
        }

        // 🔹 4️⃣ Confirmar transacción
        pg_query($connection, "COMMIT");

        echo json_encode([
            'success' => true,
            'message' => 'Gasto registrado correctamente.',
            'id_asiento' => $id_asiento,
            'detalles' => $detalles_insertados
        ]);

    } catch (Exception $e) {
        pg_query($connection, "ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }

    pg_close($connection);

} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
