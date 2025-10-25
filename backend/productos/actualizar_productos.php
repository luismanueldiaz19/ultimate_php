<?php
include '../conexion.php';
include '../utils.php';

header("Content-Type: application/json; charset=UTF-8");

try {
    // Leer el cuerpo del request
    $input = json_decode(file_get_contents("php://input"), true) ?? [];

    // Definir los campos esperados
    $fields = [
        'costo'         => $input['costo'] ?? '',
        'precio_one'    => $input['precio_one'] ?? '',
        'precio_two'    => $input['precio_two'] ?? '',
        'precio_three'  => $input['precio_three'] ?? '',
        'department'    => $input['department'] ?? '',
        'linea'         => $input['linea'] ?? '',
        'material'      => $input['material'] ?? '',
        'estilo'        => $input['estilo'] ?? '',
        'marca'         => $input['marca'] ?? '',
        'genero'        => $input['genero'] ?? '',
        'color'         => $input['color'] ?? '',
        'size'          => $input['size'] ?? '',
        'id_producto'   => $input['id_producto'] ?? ''
    ];

    // Escapar cada campo (prevención de inyección SQL)
    foreach ($fields as $key => $value) {
        $fields[$key] = pg_escape_string($conn, $value);
    }

    // Validar campos obligatorios
    $required = ['id_producto','costo','precio_one','precio_two','precio_three','department','linea','material','estilo','marca','genero','color','size'];

    $empty_fields = [];
    foreach ($required as $field) {
        if (empty($fields[$field])) {
            $empty_fields[] = $field;
        }
    }

    if (!empty($empty_fields)) {
        json_response([
            'success' => false,
            'message' => 'Campos obligatorios incompletos',
            'empty_fields' => $empty_fields
        ], 400);
        exit;
    }

    // Extraer valores finales para actualizar
    $costo        = $fields['costo'];
    $precio_one   = $fields['precio_one'];
    $precio_two   = $fields['precio_two'];
    $precio_three = $fields['precio_three'];
    $department   = $fields['department'];
    $linea        = $fields['linea'];
    $material     = $fields['material'];
    $estilo       = $fields['estilo'];
    $marca        = $fields['marca'];
    $genero       = $fields['genero'];
    $color        = $fields['color'];
    $size         = $fields['size'];
    $id_producto  = $fields['id_producto'];

    // Consulta preparada (segura)
    $sql = "UPDATE public.productos SET 
                costo = $1, 
                precio_one = $2, 
                precio_two = $3, 
                precio_three = $4,
                department = $5,
                linea = $6,
                material = $7,
                estilo = $8,
                marca = $9,
                genero = $10,
                color = $11,
                size = $12
            WHERE id_producto = $13
            RETURNING *";

    $params = [
        $costo,
        $precio_one,
        $precio_two,
        $precio_three,
        $department,
        $linea,
        $material,
        $estilo,
        $marca,
        $genero,
        $color,
        $size,
        $id_producto
    ];

    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        json_response([
            "success" => false,
            "message" => "Error al actualizar el producto: " . pg_last_error($conn)
        ], 500);
        exit;
    }

    $productoActualizado = pg_fetch_assoc($result);

    json_response([
        "success" => true,
        "message" => "Producto actualizado correctamente.",
        "producto" => $productoActualizado
    ]);

} catch (Exception $e) {
    json_response([
        "success" => false,
        "message" => "Excepción: " . $e->getMessage()
    ], 500);
}
?>
