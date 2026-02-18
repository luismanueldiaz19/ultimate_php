<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    include '../conexion.php';

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;

    if (!$id) {
        echo json_encode([
            'success' => false,
            'message' => 'ID requerido'
        ]);
        exit;
    }

    pg_query($conn, 'BEGIN');

    try {

        // 1️⃣ Obtener ruta
        $query = "
            SELECT ruta
            FROM public.design_images_items
            WHERE design_images_items_id = $1
        ";

        $result = pg_query_params($conn, $query, [$id]);

        if (!$result || pg_num_rows($result) == 0) {
            throw new Exception('Imagen no encontrada');
        }

        $row = pg_fetch_assoc($result);
        $ruta = $row['ruta'];

        // ✅ Ruta física REAL
        $filePath = __DIR__ . '/' . $ruta;

        // 2️⃣ Eliminar DB
        $delete = pg_query_params(
            $conn,
            "DELETE FROM public.design_images_items
             WHERE design_images_items_id = $1",
            [$id]
        );

        if (!$delete) {
            throw new Exception(pg_last_error($conn));
        }

        // 3️⃣ Eliminar archivo físico
        if (!empty($ruta) && file_exists($filePath)) {

            if (!unlink($filePath)) {
                throw new Exception('No se pudo eliminar el archivo');
            }
        }

        pg_query($conn, 'COMMIT');

        echo json_encode([
            'success' => true,
            'message' => 'Imagen eliminada correctamente'
        ]);

    } catch (Exception $e) {

        pg_query($conn, 'ROLLBACK');

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }

    pg_close($conn);
}
?>
