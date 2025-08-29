<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

function json_response($data, $code = 200) {
  http_response_code($code);
  echo json_encode($data);
  exit;
}
?>
