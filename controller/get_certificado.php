<?php
// CORS (dev)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 86400");

// Responde preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Seu restante:
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json; charset=UTF-8");

require('db.php');


$eventoId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  $eventoId = $input['evento_id'] ?? null;
} else {
  $eventoId = $_GET['evento_id'] ?? null;
}

$eventoId = filter_var($eventoId, FILTER_VALIDATE_INT);

if (!$eventoId) {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "evento_id inválido"]);
  exit;
}



$sql = "
SELECT
  c.codigo AS cod_certificado,
  u.nome   AS participante,
  u.email  AS email,
  IFNULL(c.status_envio, 0) AS status
FROM certificado c
JOIN usuario u ON u.ID = c.fk_Usuario_ID
JOIN atividade a ON a.ID = c.fk_Atividade_ID
WHERE a.fk_Evento_codigo = ?
ORDER BY u.nome ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $eventoId);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
  $row['cod_certificado'] = (int)$row['cod_certificado'];
  $row['status'] = (int)$row['status']; // <- força 0/1 como número
  $out[] = $row;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
