<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit;

require(__DIR__ . '/db.php'); // $conn (mysqli)

// ========= SMTP =========
$SMTP_HOST   = "smtp.gmail.com";
$SMTP_USER   = "sicad@atomicmail.io";
$SMTP_PASS   = "sicad@2025"; // <- coloque no ambiente (não no código)
$SMTP_PORT   = 587;
$SMTP_SECURE = "tls";
$FROM_EMAIL  = $SMTP_USER;
$FROM_NAME   = "SICAD";
// ========================

function respond($success, $message, $extra = []) {
  echo json_encode(array_merge([
    "success" => (bool)$success,
    "message" => (string)$message
  ], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function findAutoload(): ?string {
  $candidates = [
    __DIR__ . "/vendor/autoload.php",
    __DIR__ . "/../vendor/autoload.php",
    __DIR__ . "/../../vendor/autoload.php",
  ];
  foreach ($candidates as $c) {
    if (file_exists($c)) return $c;
  }
  return null;
}

function sendVerifyEmailPHPMailer(string $toEmail, string $token, array $smtp): array {
  // log dev opcional
  @file_put_contents(
    __DIR__ . "/verify_tokens.log",
    "[" . date("Y-m-d H:i:s") . "] to={$toEmail} token={$token}\n",
    FILE_APPEND
  );

  $autoload = findAutoload();
  if (!$autoload) {
    return ["ok" => false, "error" => "PHPMailer não encontrado. Rode: composer require phpmailer/phpmailer"];
  }
  require_once $autoload;

  try {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->CharSet = "UTF-8";
    $mail->isSMTP();
    $mail->Host       = $smtp["host"];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp["user"];
    $mail->Password   = $smtp["pass"];
    $mail->Port       = $smtp["port"];
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

// -------- valida SMTP em prod
if (!$SMTP_PASS) {
  respond(false, "Configuração SMTP_PASS ausente no servidor.");
}

// Lê JSON
$input = json_decode(file_get_contents("php://input"), true);
$nome  = trim($input["nome"] ?? "");
$email = trim($input["email"] ?? "");
$senha = (string)($input["senha"] ?? "");

if ($nome === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 6) {
  respond(false, "Dados inválidos. Verifique nome, e-mail e senha (mínimo 6 caracteres).");
}

// Verifica se já existe
$stmt = $conn->prepare("SELECT ID, email_verificado FROM usuario WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($idExistente, $emailVerificado);
$existe = $stmt->fetch();
$stmt->close();

if ($existe && (int)$emailVerificado === 1) {
  respond(false, "Este e-mail já está cadastrado.");
}

// Se existe mas não verificado, apenas reenvia token (não muda senha por segurança)
if ($existe && (int)$emailVerificado === 0) {
  $idUsuario = (int)$idExistente;
} else {
  // cria usuário novo (não verificado)
  $hashSenha = password_hash($senha, PASSWORD_DEFAULT);

  // ATENÇÃO: ajuste o nome da coluna da senha se necessário
  $stmt = $conn->prepare("INSERT INTO usuario (nome, email, senha, email_verificado) VALUES (?, ?, ?, 0)");
  $stmt->bind_param("sss", $nome, $email, $hashSenha);

  if (!$stmt->execute()) {
    $stmt->close();
    respond(false, "Não foi possível criar o usuário agora.");
  }
  $idUsuario = (int)$stmt->insert_id;
  $stmt->close();
}

// Remove token antigo
$stmt = $conn->prepare("DELETE FROM verificacao_email WHERE id_usuario = ?");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$stmt->close();

// Gera token
$token = bin2hex(random_bytes(16)); // 32 chars
$hashToken = hash("sha256", $token);

// Salva token
$stmt = $conn->prepare("
  INSERT INTO verificacao_email (id_usuario, hash_token, expira_em)
  VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 45 MINUTE))
");
$stmt->bind_param("is", $idUsuario, $hashToken);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
  respond(false, "Não foi possível gerar o token agora. Tente novamente.");
}

// Envia e-mail
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
  respond(false, "Não foi possível enviar o e-mail agora. Detalhe: " . $send["error"]);
}

respond(true, "Conta criada! Enviamos um token de verificação para seu e-mail.", [
  "needs_verification" => true,
  "email" => $email
]);