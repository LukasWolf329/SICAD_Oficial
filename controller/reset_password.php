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

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = [];
}

$token = isset($input["token"]) ? trim((string)$input["token"]) : "";
$newPassword = isset($input["password"]) ? (string)$input["password"] : "";

if ($token === "" || $newPassword === "") {
    respond(400, [
        "success" => false,
        "message" => "Token e senha são obrigatórios."
    ]);
}

if (strlen($newPassword) < 6) {
    respond(400, [
        "success" => false,
        "message" => "A senha precisa ter pelo menos 6 caracteres."
    ]);
}

if (!ctype_xdigit($token) || strlen($token) !== 32 || preg_match('/[\x00-\x1F\x7F]/', $token)) {
    respond(400, [
        "success" => false,
        "message" => "Token inválido ou expirado."
    ]);
}

if (preg_match('/\x00/', $newPassword)) {
    respond(400, [
        "success" => false,
        "message" => "Senha inválida."
    ]);
}

$hashToken = hash("sha256", $token);

$stmt = $conn->prepare("
    SELECT id_usuario
    FROM redefinicao_senha
    WHERE hash_token = ?
      AND expira_em > NOW()
    LIMIT 1
");

if (!$stmt) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao preparar consulta do token."
    ]);
}

$stmt->bind_param("s", $hashToken);

if (!$stmt->execute()) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao validar token."
    ]);
}

$stmt->bind_result($idUsuario);
$tokenValido = $stmt->fetch();
$stmt->close();

if (!$tokenValido) {
    respond(400, [
        "success" => false,
        "message" => "Token inválido ou expirado."
    ]);
}

$senhaHash = password_hash($newPassword, PASSWORD_DEFAULT);

if ($senhaHash === false) {
    respond(500, [
        "success" => false,
        "message" => "Não foi possível processar a nova senha."
    ]);
}

if (!$conn->begin_transaction()) {
    respond(500, [
        "success" => false,
        "message" => "Não foi possível iniciar a atualização da senha."
    ]);
}

try {
    $stmt = $conn->prepare("
        UPDATE usuario
        SET senha = ?
        WHERE ID = ?
    ");

    if (!$stmt) {
        throw new Exception("Erro ao preparar atualização da senha.");
    }

    $stmt->bind_param("si", $senhaHash, $idUsuario);

    if (!$stmt->execute()) {
        throw new Exception("Erro ao atualizar a senha.");
    }

    $stmt->close();

    $stmt = $conn->prepare("
        DELETE FROM redefinicao_senha
        WHERE id_usuario = ?
    ");

    if (!$stmt) {
        throw new Exception("Erro ao preparar remoção do token.");
    }

    $stmt->bind_param("i", $idUsuario);

    if (!$stmt->execute()) {
        throw new Exception("Erro ao remover o token usado.");
    }

    $stmt->close();

    $conn->commit();

    respond(200, [
        "success" => true,
        "message" => "Senha redefinida com sucesso!"
    ]);
} catch (\Throwable $e) {
    $conn->rollback();

    respond(500, [
        "success" => false,
        "message" => "Não foi possível atualizar a senha."
    ]);
}