<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

require('db.php');

$input = json_decode(file_get_contents('php://input'), true);
$evento_id = isset($input['evento_id']) ? (int)$input['evento_id'] : 0;

if ($evento_id <= 0) {
  echo json_encode(["error" => "evento_id não enviado ou inválido"], JSON_UNESCAPED_UNICODE);
  exit;
}

$response = [];

$stmt = $conn->prepare("
  SELECT DISTINCT 
    a.ID   AS atividade_id,
    a.nome AS titulo
  FROM Certificado c
  JOIN Atividade a ON c.fk_Atividade_ID = a.ID
  JOIN Evento e ON a.fk_Evento_codigo = e.codigo
  WHERE e.codigo = ?
  ORDER BY a.nome;
");

$stmt->bind_param("i", $evento_id);

if ($stmt->execute()) {
  $stmt->bind_result($atividade_id, $titulo);

  while ($stmt->fetch()) {
    $response[] = [
      "titulo" => $titulo,
      "atividade_id" => (int)$atividade_id,
    ];
  }
}

$stmt->close();
$conn->close();

echo json_encode($response, JSON_UNESCAPED_UNICODE);