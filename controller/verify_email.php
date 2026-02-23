<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit;

require(__DIR__ . '/db.php');

function respond($success, $message, $extra = []) {
  echo json_encode(array_merge([
    "success" => (bool)$success,
    "message" => (string)$message
  ], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$email = trim($input["email"] ?? "");
$token = trim($input["token"] ?? "");

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $token === "") {
  respond(false, "E-mail ou token inválido.");
}

$stmt = $conn->prepare("SELECT ID, email_verificado FROM usuario WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($idUsuario, $emailVerificado);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
  // resposta genérica pra não “vazar” se existe
  respond(false, "Token inválido ou expirado.");
}

if ((int)$emailVerificado === 1) {
  respond(true, "E-mail já estava verificado.");
}

$hashToken = hash("sha256", $token);

$stmt = $conn->prepare("
  SELECT id
  FROM verificacao_email
  WHERE id_usuario = ?
    AND hash_token = ?
    AND expira_em > NOW()
  LIMIT 1
");
$stmt->bind_param("is", $idUsuario, $hashToken);
$stmt->execute();
$stmt->bind_result($idVerif);
$okToken = $stmt->fetch();
$stmt->close();

if (!$okToken) {
  respond(false, "Token inválido ou expirado.");
}

// marca usuário como verificado
$stmt = $conn->prepare("UPDATE usuario SET email_verificado = 1 WHERE ID = ? LIMIT 1");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$stmt->close();

// apaga token
$stmt = $conn->prepare("DELETE FROM verificacao_email WHERE id_usuario = ?");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$stmt->close();

respond(true, "E-mail verificado com sucesso!");