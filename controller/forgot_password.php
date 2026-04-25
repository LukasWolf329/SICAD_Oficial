<?php
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

ini_set("display_errors", "0");
ini_set("html_errors", "0");
error_reporting(E_ALL);

require_once __DIR__ . "/db.php";


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
function respond(int $status, array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($status);

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        http_response_code(500);
        echo '{"success":false,"message":"Falha ao gerar JSON"}';
        exit;
    }

    echo $json;
    exit;
}

function sendResetEmailPHPMailer(string $toEmail, string $token): array
{
    try {
        $autoload = __DIR__ . "/vendor/autoload.php";
        if (!is_file($autoload)) {
            $autoload = dirname(__DIR__) . "/vendor/autoload.php";
        }
        if (!is_file($autoload)) {
            return ["ok" => false, "error" => "PHPMailer autoload não encontrado."];
        }

        require_once $autoload;



        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = "smtp.gmail.com";
        $mail->SMTPAuth = true;
        $mail->Username = 'sicad.certificados@gmail.com';
        $mail->Password = 'dtrt frya etbb ohhy'; // senha d

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = "UTF-8";

        $mail->setFrom('sicad.certificados@gmail.com', 'SICAD - Certificados');
        $mail->addAddress($toEmail);

        $safeToken = htmlspecialchars($token, ENT_QUOTES, "UTF-8");

        $mail->isHTML(true);
        $mail->Subject = "Redefinição de senha - SICAD";
        $mail->Body = "
            <p>Você solicitou redefinir sua senha.</p>
            <p><strong>Seu token:</strong></p>
            <p style='font-size:18px;'><code>{$safeToken}</code></p>
            <p>Abra o site e vá na tela <strong>Redefinir senha</strong> e cole esse token.</p>
            <p>Esse token expira em aproximadamente 45 minutos.</p>
            <p>Se você não solicitou isso, ignore este e-mail.</p>
        ";

        $mail->AltBody =
            "Você solicitou redefinir sua senha.\n\n" .
            "Token: {$token}\n\n" .
            "Abra o app e vá na tela 'Redefinir senha' e cole esse token.\n" .
            "Esse token expira em aproximadamente 45 minutos.\n\n" .
            "Se você não solicitou isso, ignore este e-mail.";

        $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function ($str, $level) {
            error_log("SMTP DEBUG [$level]: $str");
        };
        $mail->send();

        return ["ok" => true, "error" => null];

    } catch (\Throwable $e) {
        $errorInfo = isset($mail) ? $mail->ErrorInfo : "";

        error_log("forgot_password mailer exception: " . $e->getMessage());
        error_log("forgot_password PHPMailer ErrorInfo: " . $errorInfo);

        return [
            "ok" => false,
            "error" => $errorInfo !== "" ? $errorInfo : $e->getMessage()
        ];
    }
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = [];
}

$email = isset($input["email"]) ? strtolower(trim((string) $input["email"])) : "";

if ($email === "") {
    respond(400, [
        "success" => false,
        "message" => "E-mail é obrigatório."
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\x00-\x1F\x7F]/', $email)) {
    respond(400, [
        "success" => false,
        "message" => "E-mail inválido."
    ]);
}

$stmt = $conn->prepare("SELECT ID, email FROM usuario WHERE email = ? LIMIT 1");

if (!$stmt) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao preparar consulta."
    ]);
}

$stmt->bind_param("s", $email);

if (!$stmt->execute()) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao consultar usuário."
    ]);
}

$stmt->bind_result($idUsuario, $emailUsuario);
$usuarioExiste = $stmt->fetch();
$stmt->close();

if (!$usuarioExiste) {
    respond(200, [
        "success" => true,
        "message" => "Se este e-mail estiver cadastrado, você receberá um token para redefinir a senha."
    ]);
}

$stmt = $conn->prepare("DELETE FROM redefinicao_senha WHERE id_usuario = ?");

if (!$stmt) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao preparar limpeza de tokens."
    ]);
}

$stmt->bind_param("i", $idUsuario);

if (!$stmt->execute()) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao limpar tokens antigos."
    ]);
}

$stmt->close();

try {
    $token = bin2hex(random_bytes(16));
} catch (\Throwable $e) {
    respond(500, [
        "success" => false,
        "message" => "Não foi possível gerar o token agora."
    ]);
}

$hashToken = hash("sha256", $token);

$stmt = $conn->prepare("
    INSERT INTO redefinicao_senha (id_usuario, hash_token, expira_em)
    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 45 MINUTE))
");

if (!$stmt) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao preparar gravação do token."
    ]);
}

$stmt->bind_param("is", $idUsuario, $hashToken);

if (!$stmt->execute()) {
    respond(500, [
        "success" => false,
        "message" => "Não foi possível gerar o token agora. Tente novamente."
    ]);
}

$stmt->close();



$send = sendResetEmailPHPMailer((string) $emailUsuario, $token);

if (!$send["ok"]) {
    error_log("forgot_password.php send fail: " . $send["error"]);

    $stmtCleanup = $conn->prepare("DELETE FROM redefinicao_senha WHERE id_usuario = ?");
    if ($stmtCleanup) {
        $stmtCleanup->bind_param("i", $idUsuario);
        $stmtCleanup->execute();
        $stmtCleanup->close();
    }

    respond(500, [
        "success" => false,
        "message" => "Não foi possível enviar o e-mail agora.",
        "debug" => $send["error"]
    ]);
}

respond(200, [
    "success" => true,
    "message" => "Enviamos um token para redefinir sua senha. Verifique sua caixa de entrada e spam."
]);