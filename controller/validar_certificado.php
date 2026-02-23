<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

require('db.php'); // $conn (mysqli)

$codigo = "";

// aceita POST JSON ou GET
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  $codigo = (string)($input['codigo'] ?? '');
} else {
  $codigo = (string)($_GET['codigo'] ?? '');
}

function normalizar_codigo(string $in): ?string {
  $in = strtoupper(trim($in));
  // remove tudo que não é letra/número
  $raw = preg_replace('/[^A-Z0-9]/', '', $in);

  // nosso padrão é 20 chars sem hífen (XXXX... 20)
  if (strlen($raw) !== 20) return null;

  return implode('-', str_split($raw, 4)); // XXXX-XXXX-XXXX-XXXX-XXXX
}

$codigoNorm = normalizar_codigo($codigo);
if ($codigoNorm === null) {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "Código inválido. Use o formato XXXX-XXXX-XXXX-XXXX-XXXX."]);
  exit;
}

$sql = "
  SELECT
    c.codigo_validacao,
    c.data_emissao,
    u.nome AS nome_usuario,
    a.nome AS nome_atividade,
    a.palestrante AS nome_palestrante
  FROM certificado c
  JOIN usuario u ON u.ID = c.fk_Usuario_ID
  JOIN atividade a ON a.ID = c.fk_Atividade_ID
  WHERE c.codigo_validacao = ?
  LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $codigoNorm);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
  echo json_encode(["success" => true, "exists" => false]);
  exit;
}

echo json_encode([
  "success" => true,
  "exists" => true,
  "certificado" => [
    "codigo" => $row["codigo_validacao"],
    "data_emissao" => $row["data_emissao"],
    "nome_usuario" => $row["nome_usuario"],
    "nome_atividade" => $row["nome_atividade"],
    "nome_palestrante" => $row["nome_palestrante"],
  ]
]);
