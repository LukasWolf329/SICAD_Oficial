<?php

declare(strict_types=1);

/**
 * POST /controller/remover_evento_da_conta.php
 * Authorization: Bearer <token devolvido pelo login>
 * Content-Type: application/json
 * Corpo: {"eventoId": 123}
 *
 * Remove SOMENTE os vínculos da conta autenticada com o evento.
 * Não apaga o evento, suas atividades, certificados nem outras contas.
 *
 * Remove de gerencia o vínculo deste usuário com este evento e de participa
 * as inscrições deste usuário nas atividades deste evento. Como presenca e
 * data_hora pertencem a participa, os registros pessoais de presença dessas
 * inscrições também são removidos. Não é apenas uma ocultação visual.
 *
 * Requer o esquema fornecido, tabelas InnoDB e db.php com mysqli em $conn.
 * Não cria tabelas e não altera nem desativa chaves estrangeiras.
 * Se o esquema real tiver FKs/triggers adicionais, revise-os antes do uso.
 * O usuário é identificado pelo token; userId/email do corpo são ignorados.
 */

ob_start();

ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(E_ALL);

// Mantém o padrão dos outros endpoints. Não usa autenticação por cookie.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function removerEventoResponder(int $status, array $dados): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($status);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

final class RemoverEventoErroHttp extends RuntimeException
{
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';

if ($metodo === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($metodo !== 'POST') {
    header('Allow: POST, OPTIONS');
    removerEventoResponder(405, [
        'ok' => false,
        'erro' => 'Método não permitido. Use POST.',
    ]);
}

$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));

if ($contentType !== 'application/json') {
    removerEventoResponder(415, [
        'ok' => false,
        'erro' => 'Envie o corpo como application/json.',
    ]);
}

// Limite pequeno: este endpoint só precisa receber o ID do evento.
$raw = file_get_contents('php://input', false, null, 0, 8193);

if ($raw === false || strlen($raw) > 8192) {
    removerEventoResponder(400, [
        'ok' => false,
        'erro' => 'Corpo da requisição inválido.',
    ]);
}

try {
    $dados = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    removerEventoResponder(400, ['ok' => false, 'erro' => 'JSON inválido.']);
}

$idRecebido = is_array($dados) ? ($dados['eventoId'] ?? null) : null;

// Não permite true, 1.5, arrays ou valores fora do INT do esquema.
if (!is_int($idRecebido) && !is_string($idRecebido)) {
    removerEventoResponder(400, [
        'ok' => false,
        'erro' => 'Informe um eventoId inteiro e positivo.',
    ]);
}

$eventoId = filter_var($idRecebido, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 2147483647],
]);

if ($eventoId === false) {
    removerEventoResponder(400, [
        'ok' => false,
        'erro' => 'Informe um eventoId inteiro e positivo.',
    ]);
}

// Alguns servidores repassam Authorization por REDIRECT_HTTP_AUTHORIZATION.
$authorization = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if ($authorization === '' && function_exists('getallheaders')) {
    foreach ((getallheaders() ?: []) as $nome => $valor) {
        if (strcasecmp((string) $nome, 'Authorization') === 0) {
            $authorization = (string) $valor;
            break;
        }
    }
}

if (preg_match('/^Bearer\s+([^\s]+)$/i', trim((string) $authorization), $matches) !== 1) {
    removerEventoResponder(401, [
        'ok' => false,
        'erro' => 'Autenticação necessária. Faça login novamente.',
    ]);
}

$token = $matches[1];

if ($token === '' || strlen($token) > 255) {
    removerEventoResponder(401, [
        'ok' => false,
        'erro' => 'Token inválido. Faça login novamente.',
    ]);
}

$emTransacao = false;

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/db.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('db.php deve disponibilizar mysqli na variável $conn.');
    }

    $conn->set_charset('utf8mb4');

    // A identidade vem do token, nunca de um ID escolhido no frontend.
    // Recusa tokens duplicados e diferenças de maiúsculas/minúsculas.
    $stmt = $conn->prepare('SELECT ID, token FROM usuario WHERE token = ? LIMIT 2');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows !== 1) {
        $stmt->close();
        throw new RemoverEventoErroHttp('Token inválido. Faça login novamente.', 401);
    }

    $stmt->bind_result($usuarioId, $tokenSalvo);
    $stmt->fetch();
    $stmt->close();

    if (!is_string($tokenSalvo) || !hash_equals($tokenSalvo, $token)) {
        throw new RemoverEventoErroHttp('Token inválido. Faça login novamente.', 401);
    }

    $usuarioId = (int) $usuarioId;

    $conn->begin_transaction();
    $emTransacao = true;

    // O evento é apenas consultado/bloqueado: NÃO é excluído nem atualizado.
    $stmt = $conn->prepare('SELECT codigo, nome FROM evento WHERE codigo = ? FOR UPDATE');
    $stmt->bind_param('i', $eventoId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows !== 1) {
        $stmt->close();
        throw new RemoverEventoErroHttp(
            'Evento não encontrado. Atualize a lista de eventos.',
            404
        );
    }

    $stmt->bind_result($codigoEvento, $nomeEvento);
    $stmt->fetch();
    $stmt->close();

    // 1. Remove APENAS o vínculo de organização desta conta, se existir.
    // Ser organizador não é um requisito para remover a própria participação.
    $stmt = $conn->prepare(
        'DELETE FROM gerencia
         WHERE fk_Usuario_ID = ?
           AND fk_Evento_codigo = ?'
    );
    $stmt->bind_param('ii', $usuarioId, $eventoId);
    $stmt->execute();
    $gerenciasRemovidas = $stmt->affected_rows;
    $stmt->close();

    // 2. Remove APENAS as inscrições desta conta nas atividades deste evento.
    // A tabela atividade é usada somente para localizar o evento; DELETE p
    // não apaga atividade. Inscrições de outros usuários/eventos não são alvo.
    $stmt = $conn->prepare(
        'DELETE p
         FROM participa AS p
         INNER JOIN atividade AS a ON a.ID = p.fk_Atividade_ID
         WHERE p.fk_Usuario_ID = ?
           AND a.fk_Evento_codigo = ?'
    );
    $stmt->bind_param('ii', $usuarioId, $eventoId);
    $stmt->execute();
    $participacoesRemovidas = $stmt->affected_rows;
    $stmt->close();

    // Não usar ELSE: a mesma conta pode ser organizadora E participante.
    // Se os vínculos já não existem, repetir o pedido é um sucesso sem efeito.
    $jaDesvinculado = ($gerenciasRemovidas + $participacoesRemovidas) === 0;

    $conn->commit();
    $emTransacao = false;

    $resposta = [
        'ok' => true,
        'escopo' => 'usuario',
        'operacao' => 'remover_evento_da_conta',
        'message' => $jaDesvinculado
            ? 'Este evento já não está vinculado à sua conta.'
            : 'Evento removido apenas da sua conta.',
        'eventoId' => $eventoId,
        'usuarioId' => $usuarioId,
        'jaDesvinculado' => $jaDesvinculado,
        'vinculosRemovidos' => [
            'gerencia' => $gerenciasRemovidas,
            'participa' => $participacoesRemovidas,
        ],
    ];

    // Não revela o nome de um evento a quem não tinha nenhum vínculo com ele.
    if (!$jaDesvinculado) {
        $resposta['eventoNome'] = $nomeEvento;
    }

    removerEventoResponder(200, $resposta);
} catch (Throwable $e) {
    if ($emTransacao && isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log('[remover_evento_conta] Falha ao reverter transação.');
        }
    }

    if ($e instanceof RemoverEventoErroHttp) {
        removerEventoResponder($e->getCode(), [
            'ok' => false,
            'erro' => $e->getMessage(),
        ]);
    }

    // Não registrar o token ou o cabeçalho Authorization.
    error_log('[remover_evento_conta] ' . $e->getMessage());

    if ($e instanceof mysqli_sql_exception && (int) $e->getCode() === 1451) {
        removerEventoResponder(409, [
            'ok' => false,
            'erro' => 'Há dependências adicionais nos vínculos desta conta. '
                . 'A remoção não foi concluída. Verifique a chave estrangeira no log; '
                . 'não desative as chaves estrangeiras.',
        ]);
    }

    if ($e instanceof mysqli_sql_exception && in_array((int) $e->getCode(), [1205, 1213], true)) {
        removerEventoResponder(409, [
            'ok' => false,
            'erro' => 'Há outra alteração em andamento. Atualize a lista e tente novamente.',
        ]);
    }

    removerEventoResponder(500, [
        'ok' => false,
        'erro' => 'Não foi possível remover o evento da sua conta. Consulte o log do servidor.',
    ]);
}
