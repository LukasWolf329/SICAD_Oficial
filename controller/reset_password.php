<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit;

require(__DIR__ . '/db.php');

function respond($success, $message) {
  echo json_encode([
    "success" => (bool)$success,
    "message" => (string)$message
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$token = isset($input["token"]) ? trim($input["token"]) : "";
$newPassword = isset($input["password"]) ? $input["password"] : "";

if (!$token || strlen($newPassword) < 6) {
  respond(false, "Token inválido ou senha muito curta.");
}

// Gera hash do token recebido
$hashToken = hash("sha256", $token);

// Busca token válido e não expirado
$stmt = $conn->prepare("
  SELECT id_usuario
  FROM redefinicao_senha
  WHERE hash_token = ?
    AND expira_em > NOW()
  LIMIT 1
");
$stmt->bind_param("s", $hashToken);
$stmt->execute();
$stmt->bind_result($idUsuario);
$tokenValido = $stmt->fetch();
$stmt->close();

if (!$tokenValido) {
  respond(false, "Token inválido ou expirado.");
}

// Gera hash da nova senha
$senhaHash = password_hash($newPassword, PASSWORD_DEFAULT);

// Atualiza senha
$stmt = $conn->prepare("
  UPDATE usuario
  SET senha = ?
  WHERE ID = ?
");
$stmt->bind_param("si", $senhaHash, $idUsuario);
$okUpdate = $stmt->execute();
$stmt->close();

if (!$okUpdate) {
  respond(false, "Não foi possível atualizar a senha.");
}

// Remove token usado
$stmt = $conn->prepare("
  DELETE FROM redefinicao_senha
  WHERE id_usuario = ?
");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$stmt->close();

respond(true, "Senha redefinida com sucesso!");