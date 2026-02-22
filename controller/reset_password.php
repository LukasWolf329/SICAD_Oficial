<?php
// SICAD/reset_password.php

header("Content-Type: application/json; charset=utf-8");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit;

require('db.php');

function respond($success, $message) {
  echo json_encode([
    "success" => (bool)$success,
    "message" => (string)$message
  ], JSON_UNESCAPED_UNICODE);
  exit;
}


// Lê JSON
$input = json_decode(file_get_contents("php://input"), true);
$token = isset($input["token"]) ? trim($input["token"]) : "";
$novaSenha = isset($input["password"]) ? (string)$input["password"] : "";

if (strlen($token) < 20) {
  respond(false, "Token inválido.");
}


$hashToken = hash("sha256", $token);

// 1) Valida token + expiração
$stmt = $conn->prepare("
  SELECT id_usuario
  FROM redefinicao_senha
  WHERE hash_token = ?
    AND expira_em >= NOW()
  LIMIT 1
");
$stmt->bind_param("s", $hashToken);
$stmt->execute();
$stmt->bind_result($idUsuario);

$tokenOk = $stmt->fetch();
$stmt->close();

if (!$tokenOk) {
  respond(false, "Token inválido ou expirado.");
}

// 2) Troca a senha (hash seguro)
$senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);

// Transação (boa prática)
$conn->begin_transaction();

try {
  // Atualiza senha
  $stmt = $conn->prepare("UPDATE usuario SET senha = ? WHERE ID = ?");
  $stmt->bind_param("si", $senhaHash, $idUsuario);
  $stmt->execute();
  $stmt->close();

  // (Opcional) se você usa usuario.token para autenticação, derruba sessão antiga:
  $stmt = $conn->prepare("UPDATE usuario SET token = NULL WHERE ID = ?");
  $stmt->bind_param("i", $idUsuario);
  $stmt->execute();
  $stmt->close();

  // Apaga token (uso único)
  $stmt = $conn->prepare("DELETE FROM redefinicao_senha WHERE hash_token = ?");
  $stmt->bind_param("s", $hashToken);
  $stmt->execute();
  $stmt->close();

  $conn->commit();
} catch (Throwable $e) {
  $conn->rollback();
  respond(false, "Erro ao atualizar senha.");
}

respond(true, "Senha atualizada com sucesso.");