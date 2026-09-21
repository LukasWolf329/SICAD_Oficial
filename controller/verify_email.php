<?php
// Substitui o endpoint enviado. Não exclui contas quando o código expira.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function ve_respond(bool $success, string $message, array $extra = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    header('Allow: POST, OPTIONS');
    ve_respond(false, 'Método não permitido.', ['code' => 'METHOD_NOT_ALLOWED'], 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    ve_respond(false, 'JSON inválido.', ['code' => 'INVALID_REQUEST'], 400);
}

if (!is_array($input)
    || !is_string($input['email'] ?? null)
    || !is_string($input['token'] ?? null)) {
    ve_respond(false, 'Informe e-mail e código.', ['code' => 'INVALID_REQUEST'], 400);
}

$email = trim($input['email']);
$token = trim($input['token']);
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || $token === '' || strlen($token) > 1024) {
    ve_respond(false, 'E-mail ou código inválido.', ['code' => 'INVALID_REQUEST'], 400);
}

$transactionOpen = false;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require __DIR__ . '/db.php';
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('db.php deve disponibilizar a conexão mysqli $conn.');
    }
    $conn->set_charset('utf8mb4');
    $conn->begin_transaction();
    $transactionOpen = true;

    // A mesma linha é bloqueada pelo serviço de reenvio, evitando operações concorrentes.
    $stmt = $conn->prepare(
        'SELECT ID, email_verificado FROM usuario WHERE email = ? LIMIT 1 FOR UPDATE'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($idUsuario, $emailVerificado);
    $found = $stmt->fetch();
    $stmt->close();

    // Não informa publicamente se o endereço existe ou já está confirmado.
    if ($found !== true || (int) $emailVerificado === 1) {
        $conn->rollback();
        $transactionOpen = false;
        ve_respond(false,
            'Código inválido ou expirado. Se já validou a conta, volte e faça login.',
            ['code' => 'INVALID_OR_EXPIRED_TOKEN'], 400);
    }

    $hashToken = hash('sha256', $token);
    $stmt = $conn->prepare(
        'SELECT id FROM verificacao_email
         WHERE id_usuario = ? AND hash_token = ? AND expira_em > NOW()
         LIMIT 1 FOR UPDATE'
    );
    $stmt->bind_param('is', $idUsuario, $hashToken);
    $stmt->execute();
    $stmt->bind_result($idVerificacao);
    $valid = $stmt->fetch();
    $stmt->close();

    if ($valid !== true) {
        $conn->rollback();
        $transactionOpen = false;
        ve_respond(false, 'Código inválido ou expirado. Solicite um novo código.',
            ['code' => 'INVALID_OR_EXPIRED_TOKEN'], 400);
    }

    $stmt = $conn->prepare(
        'UPDATE usuario SET email_verificado = 1 WHERE ID = ? AND email_verificado = 0'
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) {
        $stmt->close();
        throw new RuntimeException('A confirmação não atualizou exatamente uma conta.');
    }
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM verificacao_email WHERE id_usuario = ?');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $transactionOpen = false;
    ve_respond(true, 'E-mail verificado com sucesso!', ['code' => 'EMAIL_VERIFIED']);
} catch (Throwable $e) {
    if ($transactionOpen && isset($conn) && $conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) { /* Registro abaixo. */ }
    }
    // Não registra senha, código, corpo da requisição ou resposta de autenticação.
    error_log('verify_email: ' . get_class($e) . ' / ' . $e->getCode());
    ve_respond(false, 'Não foi possível validar o e-mail agora. Tente novamente.',
        ['code' => 'INTERNAL_ERROR'], 500);
}
