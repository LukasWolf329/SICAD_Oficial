<?php
ob_start();

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
  http_response_code(204);
  exit;
}

ini_set("display_errors", "0");
ini_set("html_errors", "0");
error_reporting(E_ALL);

require_once __DIR__ . "/db.php";

// ========= SMTP =========
$SMTP_HOST = "smtp.gmail.com";
$SMTP_USER = "sicad.certificados@gmail.com";
$SMTP_PASS = "dtrt frya etbb ohhy";
$SMTP_PORT = 587;
$SMTP_SECURE = "tls";
$FROM_EMAIL = $SMTP_USER;
$FROM_NAME = "SICAD";
// ========================

function respond(bool $success, string $message, array $extra = [], int $status = 200): void
{
  if (ob_get_length()) {
    ob_clean();
  }

  http_response_code($status);

  $json = json_encode(array_merge([
    "success" => $success,
    "message" => $message
  ], $extra), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

  if ($json === false) {
    http_response_code(500);
    echo '{"success":false,"message":"Falha ao gerar JSON"}';
    exit;
  }

  echo $json;
  exit;
}

function loadPHPMailer(): void
{
  require_once __DIR__ . "/vendor/autoload.php";
}

function sendVerifyEmailPHPMailer(string $toEmail, string $token, array $smtp): array
{
  @file_put_contents(
    __DIR__ . "/verify_tokens.log",
    "[" . date("Y-m-d H:i:s") . "] to={$toEmail} token={$token}\n",
    FILE_APPEND
  );

  loadPHPMailer();

  try {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->CharSet = "UTF-8";
    $mail->isSMTP();
    $mail->Host = $smtp["host"];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp["user"];
    $mail->Password = $smtp["pass"];
    $mail->Port = $smtp["port"];
    $mail->SMTPSecure = ($smtp["secure"] === "ssl")
      ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
      : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

    $mail->setFrom($smtp["from_email"], $smtp["from_name"]);
    $mail->addAddress($toEmail);

    $mail->isHTML(true);
    $mail->Subject = "Verificação de e-mail - SICAD";
    $mail->Body = "
      <p>Olá! Para ativar sua conta, use o token abaixo:</p>
      <p style='font-size:18px;'><code>{$token}</code></p>
      <p>Abra o app e cole esse token.</p>
      <p>Esse token expira em aproximadamente 45 minutos.</p>
    ";
    $mail->AltBody =
      "Para ativar sua conta, use o token abaixo:\n\n" .
      "Token: {$token}\n\n" .
      "Esse token expira em ~45 minutos.\n";

    $mail->send();
    return ["ok" => true, "error" => null];
  } catch (\Throwable $e) {
    return ["ok" => false, "error" => $e->getMessage()];
  }
}

if (!$SMTP_PASS) {
  respond(false, "Configuração SMTP_PASS ausente no servidor.", [], 500);
}

// limpa usuários não verificados com token expirado
$stmt = $conn->prepare("
  DELETE u, v
  FROM usuario u
  JOIN verificacao_email v ON v.id_usuario = u.ID
  WHERE u.email_verificado = 0
    AND v.expira_em <= NOW()
");

if ($stmt) {
  $stmt->execute();
  $stmt->close();
}


$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
  respond(false, "Body JSON inválido.", [], 400);
}

$nome = trim((string) ($input["nome"] ?? ""));
$email = mb_strtolower(trim((string) ($input["email"] ?? "")));
$senha = (string) ($input["senha"] ?? "");

if ($nome === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 6) {
  respond(false, "Dados inválidos. Verifique nome, e-mail e senha (mínimo 6 caracteres).", [], 400);
}

$stmt = $conn->prepare("SELECT ID, email_verificado FROM usuario WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($idExistente, $emailVerificado);
$existe = $stmt->fetch();
$stmt->close();

if ($existe && (int) $emailVerificado === 1) {
  respond(false, "Este e-mail já está cadastrado.", [], 409);
}

if ($existe && (int) $emailVerificado === 0) {
  $idUsuario = (int) $idExistente;
} else {
  $hashSenha = password_hash($senha, PASSWORD_DEFAULT);

  $stmt = $conn->prepare("INSERT INTO usuario (nome, email, senha, email_verificado) VALUES (?, ?, ?, 0)");
  $stmt->bind_param("sss", $nome, $email, $hashSenha);

  if (!$stmt->execute()) {
    $stmt->close();
    respond(false, "Não foi possível criar o usuário agora.", [], 500);
  }

  $idUsuario = (int) $stmt->insert_id;
  $stmt->close();
}

$stmt = $conn->prepare("DELETE FROM verificacao_email WHERE id_usuario = ?");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$stmt->close();

$token = bin2hex(random_bytes(16));
$hashToken = hash("sha256", $token);

$stmt = $conn->prepare("
  INSERT INTO verificacao_email (id_usuario, hash_token, expira_em)
  VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 45 MINUTE))
");
$stmt->bind_param("is", $idUsuario, $hashToken);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
  respond(false, "Não foi possível gerar o token agora. Tente novamente.", [], 500);
}

$smtp = [
  "host" => $SMTP_HOST,
  "user" => $SMTP_USER,
  "pass" => $SMTP_PASS,
  "port" => $SMTP_PORT,
  "secure" => $SMTP_SECURE,
  "from_email" => $FROM_EMAIL,
  "from_name" => $FROM_NAME,
];

$send = sendVerifyEmailPHPMailer($email, $token, $smtp);

if (!$send["ok"]) {
  respond(false, "Não foi possível enviar o e-mail agora. Detalhe: " . $send["error"], [], 500);
}

respond(true, "Conta criada! Enviamos um token de verificação para seu e-mail.", [
  "needs_verification" => true,
  "email" => $email
], 200);