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

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$email = isset($data['email']) ? mb_strtolower(trim((string)$data['email'])) : '';
$senha = isset($data['senha']) ? (string)$data['senha'] : '';

if ($email === '' || $senha === '') {
    respond(400, [
        "success" => false,
        "message" => "Por favor, preencha todos os campos"
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, [
        "success" => false,
        "message" => "Credenciais inválidas"
    ]);
}

if (preg_match('/[\x00-\x1F\x7F]/', $email) || preg_match('/\x00/', $senha)) {
    respond(400, [
        "success" => false,
        "message" => "Credenciais inválidas"
    ]);
}

// limpa expirados
$stmt = $conn->prepare("
  DELETE u, v
  FROM usuario u
  JOIN verificacao_email v ON v.id_usuario = u.ID
  WHERE u.email_verificado = 0
    AND v.expira_em <= NOW()
");
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('SELECT ID, nome, email, senha, email_verificado FROM usuario WHERE email = ? LIMIT 1');

if (!$stmt) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao preparar consulta",
        "debug" => $conn->error
    ]);
}

$stmt->bind_param('s', $email);

if (!$stmt->execute()) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao executar consulta",
        "debug" => $stmt->error
    ]);
}

$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    respond(404, [
        "success" => false,
        "code" => "USER_NOT_FOUND",
        "message" => "Usuário não encontrado"
    ]);
}

$usuario = $result->fetch_assoc();

if (!password_verify($senha, $usuario['senha'])) {
    respond(401, [
        "success" => false,
        "code" => "PASSWORD_INVALID",
        "message" => "Senha inválida"
    ]);
}

if ((int)$usuario['email_verificado'] === 0) {
    respond(403, [
        "success" => false,
        "code" => "EMAIL_NOT_VERIFIED",
        "message" => "Seu e-mail ainda não foi verificado. Digite o token enviado para seu e-mail."
    ]);
}

$token = bin2hex(random_bytes(32));

$stmtToken = $conn->prepare("UPDATE usuario SET token = ? WHERE ID = ?");

if (!$stmtToken) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao preparar atualização do token",
        "debug" => $conn->error
    ]);
}

$stmtToken->bind_param("si", $token, $usuario['ID']);

if (!$stmtToken->execute()) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao salvar token",
        "debug" => $stmtToken->error
    ]);
}

respond(200, [
    "success" => true,
    "token" => $token,
    "usuario" => [
        "id" => $usuario['ID'],
        "nome" => $usuario['nome'],
        "email" => $usuario['email']
    ]
]);