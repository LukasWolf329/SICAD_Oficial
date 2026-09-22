<?php

declare(strict_types=1);

/**
 * Funções compartilhadas pelos endpoints de certificados.
 * Aplicativo externo: seleção por email + atividade_id, sem comparar IDs entre sistemas.
 * Painel SICAD: mantém Authorization: Bearer <usuario.token> para acesso por código.
 * IMPORTANTE: o e-mail recebido é um filtro, NÃO uma autenticação. O fluxo externo
 * precisa de autenticação/autorização do aplicativo de origem antes de uso público.
 */
final class SicadCertificadoHttpError extends RuntimeException
{
}

function sc_responder(int $status, array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function sc_ler_json(): array
{
    $tipo = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($tipo !== 'application/json') {
        throw new SicadCertificadoHttpError('Envie a requisição como application/json.', 415);
    }
    $raw = file_get_contents('php://input', false, null, 0, 8193);
    if ($raw === false || strlen($raw) > 8192) {
        throw new SicadCertificadoHttpError('Corpo da requisição inválido ou muito grande.', 413);
    }
    try {
        $dados = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new SicadCertificadoHttpError('JSON inválido.', 400);
    }
    if (!is_array($dados) || substr(ltrim($raw), 0, 1) !== '{') {
        throw new SicadCertificadoHttpError('Envie um objeto JSON.', 400);
    }
    return $dados;
}

function sc_id($valor, string $nome): int
{
    if ((!is_int($valor) && !is_string($valor))
        || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $valor)) {
        throw new SicadCertificadoHttpError($nome . ' deve ser um inteiro positivo.', 400);
    }
    $id = filter_var($valor, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647],
    ]);
    if ($id === false) {
        throw new SicadCertificadoHttpError($nome . ' está fora do intervalo permitido.', 400);
    }
    return (int) $id;
}

function sc_token(): string
{
    $authorization = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($authorization === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $nome => $valor) {
            if (strcasecmp((string) $nome, 'Authorization') === 0) {
                $authorization = (string) $valor;
                break;
            }
        }
    }
    if (!is_string($authorization)
        || !preg_match('/^Bearer[ \t]+([^\s]+)$/iD', trim($authorization), $m)
        || strlen($m[1]) > 255) {
        throw new SicadCertificadoHttpError('Autenticação necessária. Faça login novamente.', 401);
    }
    return $m[1];
}

function sc_conectar(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require __DIR__ . '/db.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('db.php deve disponibilizar uma conexão MySQLi em $conn.');
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function sc_usuario_autenticado(mysqli $conn, string $token): int
{
    // LIMIT 2 rejeita tokens duplicados em vez de escolher arbitrariamente um usuário.
    $stmt = $conn->prepare('SELECT ID, token FROM usuario WHERE token = ? LIMIT 2');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows !== 1) {
        $stmt->close();
        throw new SicadCertificadoHttpError('Sessão inválida. Faça login novamente.', 401);
    }
    $stmt->bind_result($id, $salvo);
    $stmt->fetch();
    $stmt->close();
    // Também recusa variação de maiúsculas/minúsculas em collation case-insensitive.
    if (!is_string($salvo) || !hash_equals($salvo, $token)) {
        throw new SicadCertificadoHttpError('Sessão inválida. Faça login novamente.', 401);
    }
    return (int) $id;
}

/** Valida o endereço recebido; não autentica quem o enviou. */
function sc_email($valor): string
{
    if (!is_string($valor)) {
        throw new SicadCertificadoHttpError('email não informado ou inválido.', 400);
    }
    $email = trim($valor);
    if ($email === '' || strlen($email) > 254
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new SicadCertificadoHttpError('email não informado ou inválido.', 400);
    }
    return $email;
}

/** Recusa parâmetros de outro aplicativo que poderiam confundir a seleção. */
function sc_rejeitar_ids_usuario(array $input): void
{
    foreach (['userId', 'user_id', 'usuarioId', 'usuario_id', 'id_usuario', 'fk_Usuario_ID'] as $chave) {
        if (array_key_exists($chave, $input)) {
            throw new SicadCertificadoHttpError(
                'Neste fluxo, envie email, não o ID do usuário de outro sistema.', 400
            );
        }
    }
}

/**
 * O e-mail localiza o cadastro no SICAD. IDs aparecem só nos JOINs internos.
 * event_id é opcional; quando informado, a atividade precisa pertencer a esse evento.
 * LEFT JOIN diferencia presença sem certificado de ausência de vínculo/presença.
 * Se houver mais de uma emissão, mantém a regra anterior: maior código.
 */
function sc_certificado_por_email(
    mysqli $conn, string $email, int $atividadeId, ?int $eventoId = null
): int {
    $sql = 'SELECT c.codigo
            FROM usuario u
            JOIN participa p ON p.fk_Usuario_ID = u.ID
            JOIN atividade a ON a.ID = p.fk_Atividade_ID
            LEFT JOIN certificado c ON c.fk_Usuario_ID = u.ID
                                   AND c.fk_Atividade_ID = a.ID
            WHERE u.email = ? AND a.ID = ? AND p.presenca = 1';
    if ($eventoId !== null) {
        $sql .= ' AND a.fk_Evento_codigo = ?';
    }
    $sql .= ' ORDER BY c.codigo DESC LIMIT 1';

    $stmt = $conn->prepare($sql);
    if ($eventoId !== null) {
        $stmt->bind_param('sii', $email, $atividadeId, $eventoId);
    } else {
        $stmt->bind_param('si', $email, $atividadeId);
    }
    $stmt->execute();
    $stmt->bind_result($codigo);
    $encontrado = $stmt->fetch();
    $stmt->close();
    if ($encontrado !== true) {
        throw new SicadCertificadoHttpError(
            'Atividade indisponível para este e-mail ou sem presença confirmada no evento informado.', 404
        );
    }
    if ($codigo === null) {
        throw new SicadCertificadoHttpError(
            'Certificado ainda não emitido para este e-mail nesta atividade. Solicite a emissão ao organizador.', 404
        );
    }
    return (int) $codigo;
}

/**
 * Compatibilidade com o painel: cod_certificado/codigo continuam aceitos.
 * Download: titular presente OU organizador vinculado ao evento.
 * E-mail: somente organizador vinculado ao evento, para o e-mail cadastrado do titular.
 * Nos dois casos, a presença do titular é obrigatória.
 */
function sc_autorizar_codigo(mysqli $conn, int $usuarioId, int $codigo, bool $download): void
{
    $stmt = $conn->prepare(
        'SELECT c.fk_Usuario_ID, a.fk_Evento_codigo, COALESCE(p.presenca, 0)
         FROM certificado c
         JOIN atividade a ON a.ID = c.fk_Atividade_ID
         LEFT JOIN participa p ON p.fk_Atividade_ID = c.fk_Atividade_ID
                              AND p.fk_Usuario_ID = c.fk_Usuario_ID
         WHERE c.codigo = ? LIMIT 1'
    );
    $stmt->bind_param('i', $codigo);
    $stmt->execute();
    $stmt->bind_result($titular, $eventoId, $presenca);
    $found = $stmt->fetch();
    $stmt->close();
    if ($found !== true) {
        throw new SicadCertificadoHttpError('Certificado indisponível para esta conta.', 404);
    }
    $organizador = false;
    if (!$download || (int) $titular !== $usuarioId) {
        $stmt = $conn->prepare(
            'SELECT 1 FROM gerencia WHERE fk_Usuario_ID = ? AND fk_Evento_codigo = ? LIMIT 1'
        );
        $stmt->bind_param('ii', $usuarioId, $eventoId);
        $stmt->execute();
        $stmt->store_result();
        $organizador = $stmt->num_rows === 1;
        $stmt->close();
    }
    if ((!$download && !$organizador)
        || ($download && (int) $titular !== $usuarioId && !$organizador)) {
        throw new SicadCertificadoHttpError('Certificado indisponível para esta conta.', 403);
    }
    if ((int) $presenca !== 1) {
        throw new SicadCertificadoHttpError('O titular não tem presença confirmada nesta atividade.', 403);
    }
}
