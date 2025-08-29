<?php
$host = "localhost";
$port = "5432";
$dbname = "tejidos_tropical";
$user = "postgres";
$password = "123456";

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
  http_response_code(500);
  echo json_encode(["error" => "Error de conexión a la base de datos"]);
  exit;
}
?>
