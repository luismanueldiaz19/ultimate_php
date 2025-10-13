<?php
include '../conexion.php';
include '../utils.php';

function generarAsientoContable($conn, $tipoAsiento, $referenciaId, $usuarioId, $descripcion, $movimientos = []) {
    pg_query($conn, "BEGIN");

    try {
        // 1️⃣ Insertar encabezado del asiento
        $res = pg_query_params($conn, "
            INSERT INTO asientos_contables (tipo_asiento, referencia_id, descripcion, creado_por)
            VALUES ($1, $2, $3, $4)
            RETURNING id_asiento
        ", [$tipoAsiento, $referenciaId, $descripcion, $usuarioId]);

        $asiento = pg_fetch_assoc($res);
        $asientoId = $asiento['id_asiento'];

        $totalDebito = 0;
        $totalCredito = 0;

        // 2️⃣ Insertar movimientos (detalle)
        foreach ($movimientos as $m) {
            pg_query_params($conn, "
                INSERT INTO detalle_asiento (id_asiento, id_cuenta, descripcion, tipo_movimiento, monto)
                VALUES ($1, $2, $3, $4, $5)
            ", [
                $asientoId,
                $m['id_cuenta'],
                $m['descripcion'],
                $m['tipo'],
                $m['monto']
            ]);

            if ($m['tipo'] === 'D') $totalDebito += $m['monto'];
            if ($m['tipo'] === 'C') $totalCredito += $m['monto'];
        }

        // 3️⃣ Actualizar totales
        pg_query_params($conn, "
            UPDATE asientos_contables
            SET total_debito = $1, total_credito = $2
            WHERE id_asiento = $3
        ", [$totalDebito, $totalCredito, $asientoId]);

        pg_query($conn, "COMMIT");

        return [
            "success" => true,
            "message" => "Asiento contable generado correctamente",
            "id_asiento" => $asientoId
        ];

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        return [
            "success" => false,
            "message" => "Error al generar asiento: " . $e->getMessage()
        ];
    }
}
