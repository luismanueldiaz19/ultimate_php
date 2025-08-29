<?php
function registrarAuditoria($conn, $usuarioId, $accion, $tabla, $datosAnteriores = null, $datosNuevos = null) {
  $sql = "INSERT INTO auditoria (id_usuario, accion, tabla, datos_anteriores, datos_nuevos)
          VALUES ($1, $2, $3, $4, $5)";
  pg_query_params($conn, $sql, [
    $usuarioId,
    $accion,
    $tabla,
    json_encode($datosAnteriores),
    json_encode($datosNuevos)
  ]);
}
