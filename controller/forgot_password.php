<?php
// SICAD/forgot_password.php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") exit;

require(__DIR__ . '/db.php'); // precisa criar $conn (mysqli)

// ========= SMTP =========
$SMTP_HOST   = "smtp.gmail.com";
$SMTP_USER   = "lukasjuliuswolf@gmail.com";

// ⚠️ NÃO deixe a senha no código.
// Coloque em variável de ambiente: SMTP_PASS
$SMTP_PASS   = "yceb hddd ucpn rgto"; 

$SMTP_PORT   = 587;
$SMTP_SECURE = "tls";
$FROM_EMAIL  = "lukasjuliuswolf@gmail.com";
$FROM_NAME   = "SICAD";
// =========================

function respond($success, $message) {
  echo json_encode([
    "success" => (bool)$success,
    "message" => (string)$message
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

function sendResetEmailPHPMailer(string $toEmail, string $token, array $smtpConfig): array {
  // Log em DEV (ajuda a testar)
  @file_put_contents(
    __DIR__ . "/reset_links.log",
    "[" . date("Y-m-d H:i:s") . "] to={$toEmail} token={$token}\n",
    FILE_APPEND
  );

  // autoload
  $autoloadCandidates = [
    __DIR__ . "/vendor/autoload.php",
    __DIR__ . "/../vendor/autoload.php",
    __DIR__ . "/../../vendor/autoload.php",
  ];

  $autoload = null;
  foreach ($autoloadCandidates as $c) {
    if (file_exists($c)) { $autoload = $c; break; }
  }

  if (!$autoload) {
    return ["ok" => false, "error" => "PHPMailer não encontrado (vendor/autoload.php). Rode: composer require phpmailer/phpmailer"];
  }

  require_once $autoload;

  try {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->CharSet = "UTF-8";
    $mail->isSMTP();
    $mail->Host       = $smtpConfig["host"];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpConfig["user"];
    $mail->Password   = $smtpConfig["pass"];
    $mail->Port       = $smtpConfig["port"];

    if ($smtpConfig["secure"] === "ssl") {
      $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } else {
      $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->setFrom($smtpConfig["from_email"], $smtpConfig["from_name"]);
    $mail->addAddress($toEmail);

    $mail->isHTML(true);
    $mail->Subject = "Redefinição de senha - SICAD";

    // ✅ SEM LINK: só token e instrução de colar no app
    $mail->Body = "
      <p>Você solicitou redefinir sua senha.</p>
      <p><b>Seu token:</b></p>
      <p style='font-size:18px;'><code>{$token}</code></p>
      <p>Abra o app e vá na tela <b>Redefinir senha</b> e cole esse token.</p>
      <p>Esse token expira em aproximadamente 45 minutos.</p>
      <p>Se você não solicitou isso, ignore este e-mail.</p>
    ";

    // versão texto (caso o cliente de e-mail não suporte HTML)
    $mail->AltBody =
      "Você solicitou redefinir sua senha.\n\n" .
      "Token: {$token}\n\n" .
      "Abra o app e vá na tela 'Redefinir senha' e cole esse token.\n" .
      "Expira em ~45 minutos.\n";

    $mail->send();
    return ["ok" => true, "error" => null];
  } catch (\Throwable $e) {
    return ["ok" => false, "error" => $e->getMessage()];
  }
}

// Lê JSON
$input = json_decode(file_get_contents("php://input"), true);
$email = isset($input["email"]) ? trim($input["email"]) : "";

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  respond(false, "E-mail inválido.");
}

// Busca usuário
$stmt = $conn->prepare("SELECT ID, email FROM usuario WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($idUsuario, $emailUsuario);
$usuarioExiste = $stmt->fetch();
$stmt->close();

// Mensagem padrão (pra não revelar se email existe)
if (!$usuarioExiste) {
  respond(true, "Se este e-mail estiver cadastrado, você receberá um token para redefinir a senha.");
}

// Remove tokens antigos
$stmt = $conn->prepare("DELETE FROM redefinicao_senha WHERE id_usuario = ?");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$stmt->close();

// Token (usei 16 bytes pra ficar menor e mais fácil de copiar/colar)
$token = bin2hex(random_bytes(16));  // 32 chars (bem mais amigável)
$hashToken = hash("sha256", $token);

// Salva token
$stmt = $conn->prepare("
  INSERT INTO redefinicao_senha (id_usuario, hash_token, expira_em)
  VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 45 MINUTE))
");
$stmt->bind_param("is", $idUsuario, $hashToken);
$okInsert = $stmt->execute();
$stmt->close();

if (!$okInsert) {
  respond(false, "Não foi possível gerar o token agora. Tente novamente.");
}

$smtp = [
  "host" => $SMTP_HOST,
  "user" => $SMTP_USER,
  "pass" => $SMTP_PASS,
  "port" => $SMTP_PORT,
  "secure" => $SMTP_SECURE,
  "from_email" => $FROM_EMAIL,
  "from_name" => $FROM_NAME
];

$send = sendResetEmailPHPMailer($emailUsuario, $token, $smtp);

if (!$send["ok"]) {
  respond(false, "Não foi possível enviar o e-mail agora. Detalhe: " . $send["error"]);
}

respond(true, "Enviamos um token para redefinir sua senha. Verifique sua caixa de entrada e spam.");