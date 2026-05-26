<?php
ob_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');
header('X-Get-Certificado-Version: sicad-2026-05-19-sync-importados');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code(204);
    exit;
}

ini_set('display_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

function respond(int $status, array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    $payload['versao_get_certificado'] = 'sicad-2026-05-19-sync-importados';

    http_response_code($status);

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    if ($json === false) {
        http_response_code(500);
        echo '{"success":false,"message":"Falha ao gerar JSON"}';
        exit;
    }

    echo $json;
    exit;
}

function qi(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function preparar(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar SQL: ' . $conn->error . ' | SQL: ' . $sql);
    }

    return $stmt;
}

function executar(mysqli_stmt $stmt, string $rotulo = 'consulta'): void
{
    if (!$stmt->execute()) {
        throw new RuntimeException('Erro ao executar ' . $rotulo . ': ' . $stmt->error);
    }
}

function resolver_tabelas(mysqli $conn): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $result = $conn->query('SHOW TABLES');

    if (!$result) {
        throw new RuntimeException('Erro ao listar tabelas: ' . $conn->error);
    }

    $cache = [];

    while ($row = $result->fetch_array(MYSQLI_NUM)) {
        $nome = (string) $row[0];
        $cache[strtolower($nome)] = $nome;
    }

    $result->free();

    return $cache;
}

function resolver_tabela(mysqli $conn, array $candidatos): string
{
    $tabelas = resolver_tabelas($conn);

    foreach ($candidatos as $candidato) {
        $chave = strtolower($candidato);

        if (isset($tabelas[$chave])) {
            return $tabelas[$chave];
        }
    }

    throw new RuntimeException('Tabela não encontrada: ' . implode(', ', $candidatos));
}

function colunas_tabela(mysqli $conn, string $tabela): array
{
    $stmt = preparar($conn, 'SHOW COLUMNS FROM ' . qi($tabela));
    executar($stmt, 'listagem de colunas da tabela ' . $tabela);

    $field = null;
    $type = null;
    $null = null;
    $key = null;
    $default = null;
    $extra = null;

    $stmt->bind_result($field, $type, $null, $key, $default, $extra);

    $colunas = [];

    while ($stmt->fetch()) {
        $nome = (string) $field;
        $colunas[strtolower($nome)] = [
            'Field' => $nome,
            'Type' => (string) $type,
            'Null' => (string) $null,
            'Key' => (string) $key,
            'Default' => $default,
            'Extra' => (string) $extra,
        ];
    }

    $stmt->free_result();
    $stmt->close();

    return $colunas;
}

function coluna_existe(mysqli $conn, string $tabela, string $coluna): bool
{
    static $cache = [];
    $chave = strtolower($tabela);

    if (!isset($cache[$chave])) {
        $cache[$chave] = colunas_tabela($conn, $tabela);
    }

    return isset($cache[$chave][strtolower($coluna)]);
}

function gerar_codigo_validacao_unico(mysqli $conn, string $tCertificado): string
{
    for ($i = 0; $i < 20; $i++) {
        $codigo = strtoupper(bin2hex(random_bytes(12)));
        $stmt = preparar($conn, 'SELECT 1 FROM ' . qi($tCertificado) . ' WHERE `codigo_validacao` = ? LIMIT 1');
        $stmt->bind_param('s', $codigo);
        executar($stmt, 'consulta de código de validação');

        $existe = null;
        $stmt->bind_result($existe);
        $encontrou = $stmt->fetch();
        $stmt->free_result();
        $stmt->close();

        if (!$encontrou) {
            return $codigo;
        }
    }

    throw new RuntimeException('Não foi possível gerar código de validação único.');
}

function obter_evento_id(): int
{
    $raw = file_get_contents('php://input');
    $json = null;

    if (is_string($raw) && trim($raw) !== '') {
        $json = json_decode($raw, true);
    }

    $valor = null;

    if (is_array($json) && isset($json['evento_id'])) {
        $valor = $json['evento_id'];
    } elseif (isset($_POST['evento_id'])) {
        $valor = $_POST['evento_id'];
    } elseif (isset($_GET['evento_id'])) {
        $valor = $_GET['evento_id'];
    }

    if ($valor === null || !ctype_digit((string) $valor)) {
        respond(400, [
            'success' => false,
            'message' => 'evento_id não enviado ou inválido'
        ]);
    }

    return (int) $valor;
}

function sincronizar_certificados_do_evento(
    mysqli $conn,
    int $eventoId,
    string $tUsuario,
    string $tAtividade,
    string $tParticipa,
    string $tCertificado
): int {
    $certificadoTemCodigoValidacao = coluna_existe($conn, $tCertificado, 'codigo_validacao');

    $sqlPendentes = '
        SELECT
            p.`fk_Usuario_ID`,
            p.`fk_Atividade_ID`,
            u.`nome`,
            a.`nome`,
            COALESCE(a.`carga_horaria`, 0)
        FROM ' . qi($tParticipa) . ' AS p
        JOIN ' . qi($tAtividade) . ' AS a
          ON a.`ID` = p.`fk_Atividade_ID`
        JOIN ' . qi($tUsuario) . ' AS u
          ON u.`ID` = p.`fk_Usuario_ID`
        LEFT JOIN ' . qi($tCertificado) . ' AS c
          ON c.`fk_Usuario_ID` = p.`fk_Usuario_ID`
         AND c.`fk_Atividade_ID` = p.`fk_Atividade_ID`
        WHERE a.`fk_Evento_codigo` = ?
          AND c.`codigo` IS NULL
        ORDER BY u.`nome`, a.`nome`
    ';

    $stmtPendentes = preparar($conn, $sqlPendentes);
    $stmtPendentes->bind_param('i', $eventoId);
    executar($stmtPendentes, 'consulta de certificados pendentes');

    $usuarioId = null;
    $atividadeId = null;
    $usuarioNome = null;
    $atividadeNome = null;
    $cargaHoraria = null;

    $stmtPendentes->bind_result($usuarioId, $atividadeId, $usuarioNome, $atividadeNome, $cargaHoraria);

    $pendentes = [];

    while ($stmtPendentes->fetch()) {
        $pendentes[] = [
            'usuario_id' => (int) $usuarioId,
            'atividade_id' => (int) $atividadeId,
            'usuario_nome' => (string) $usuarioNome,
            'atividade_nome' => (string) $atividadeNome,
            'carga_horaria' => (int) $cargaHoraria,
        ];
    }

    $stmtPendentes->free_result();
    $stmtPendentes->close();

    if (count($pendentes) === 0) {
        return 0;
    }

    if ($certificadoTemCodigoValidacao) {
        $stmtInsert = preparar(
            $conn,
            'INSERT INTO ' . qi($tCertificado) . ' (`data_emissao`, `texto_certificado`, `descricao`, `carga_horaria`, `fk_Usuario_ID`, `fk_Atividade_ID`, `codigo_validacao`) VALUES (CURDATE(), ?, ?, ?, ?, ?, ?)'
        );
    } else {
        $stmtInsert = preparar(
            $conn,
            'INSERT INTO ' . qi($tCertificado) . ' (`data_emissao`, `texto_certificado`, `descricao`, `carga_horaria`, `fk_Usuario_ID`, `fk_Atividade_ID`) VALUES (CURDATE(), ?, ?, ?, ?, ?)'
        );
    }

    $criados = 0;

    foreach ($pendentes as $pendente) {
        $texto = 'Certificamos que ' . $pendente['usuario_nome'] . ' participou da atividade "' . $pendente['atividade_nome'] . '".';
        $descricao = $pendente['atividade_nome'];
        $carga = (int) $pendente['carga_horaria'];
        $u = (int) $pendente['usuario_id'];
        $a = (int) $pendente['atividade_id'];

        if ($certificadoTemCodigoValidacao) {
            $codigoValidacao = gerar_codigo_validacao_unico($conn, $tCertificado);
            $stmtInsert->bind_param('ssiiis', $texto, $descricao, $carga, $u, $a, $codigoValidacao);
        } else {
            $stmtInsert->bind_param('ssiii', $texto, $descricao, $carga, $u, $a);
        }

        executar($stmtInsert, 'criação automática de certificado');
        $criados++;
    }

    $stmtInsert->close();

    return $criados;
}

try {
    require_once __DIR__ . '/db.php';

    if (ob_get_length()) {
        ob_clean();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, [
            'success' => false,
            'message' => 'Método não permitido'
        ]);
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        respond(500, [
            'success' => false,
            'message' => 'Conexão com o banco não encontrada'
        ]);
    }

    $conn->set_charset('utf8mb4');

    $eventoId = obter_evento_id();

    $tEvento = resolver_tabela($conn, ['evento', 'Evento']);
    $tUsuario = resolver_tabela($conn, ['usuario', 'Usuario']);
    $tAtividade = resolver_tabela($conn, ['atividade', 'Atividade']);
    $tParticipa = resolver_tabela($conn, ['participa', 'Participa']);
    $tCertificado = resolver_tabela($conn, ['certificado', 'Certificado']);

    $stmtEvento = preparar($conn, 'SELECT `codigo` FROM ' . qi($tEvento) . ' WHERE `codigo` = ? LIMIT 1');
    $stmtEvento->bind_param('i', $eventoId);
    executar($stmtEvento, 'consulta do evento');

    $eventoEncontrado = null;
    $stmtEvento->bind_result($eventoEncontrado);
    $temEvento = $stmtEvento->fetch();
    $stmtEvento->free_result();
    $stmtEvento->close();

    if (!$temEvento) {
        respond(404, [
            'success' => false,
            'message' => 'Evento não encontrado'
        ]);
    }

    $conn->begin_transaction();
    $certificadosCriadosAutomaticamente = sincronizar_certificados_do_evento(
        $conn,
        $eventoId,
        $tUsuario,
        $tAtividade,
        $tParticipa,
        $tCertificado
    );
    $conn->commit();

    $temStatusEnvio = coluna_existe($conn, $tAtividade, 'status_envio');
    $statusExpr = $temStatusEnvio ? 'COALESCE(a.`status_envio`, 0)' : '0';

    $sqlLista = '
        SELECT
            c.`codigo`,
            u.`nome`,
            u.`email`,
            ' . $statusExpr . ',
            a.`ID`,
            a.`nome`
        FROM ' . qi($tCertificado) . ' AS c
        JOIN ' . qi($tUsuario) . ' AS u
          ON u.`ID` = c.`fk_Usuario_ID`
        JOIN ' . qi($tAtividade) . ' AS a
          ON a.`ID` = c.`fk_Atividade_ID`
        WHERE a.`fk_Evento_codigo` = ?
        ORDER BY u.`nome`, a.`nome`, c.`codigo`
    ';

    $stmtLista = preparar($conn, $sqlLista);
    $stmtLista->bind_param('i', $eventoId);
    executar($stmtLista, 'listagem de certificados');

    $codigo = null;
    $participante = null;
    $email = null;
    $status = null;
    $atividadeId = null;
    $atividadeNome = null;

    $stmtLista->bind_result($codigo, $participante, $email, $status, $atividadeId, $atividadeNome);

    $certificados = [];

    while ($stmtLista->fetch()) {
        $certificados[] = [
            'cod_certificado' => (int) $codigo,
            'participante' => (string) $participante,
            'email' => (string) $email,
            'status' => (int) $status,
            'atividade_id' => (int) $atividadeId,
            'atividade' => (string) $atividadeNome,
        ];
    }

    $stmtLista->free_result();
    $stmtLista->close();

    respond(200, [
        'success' => true,
        'certificados' => $certificados,
        'criados_automaticamente' => $certificadosCriadosAutomaticamente,
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log('Erro no rollback do get_certificado.php: ' . $rollbackError->getMessage());
        }
    }

    error_log('get_certificado.php: ' . $e->getMessage());

    respond(500, [
        'success' => false,
        'message' => 'Erro interno ao carregar certificados',
        'debug' => isset($_GET['debug']) && $_GET['debug'] === '1'
            ? [
                'erro' => $e->getMessage(),
                'arquivo' => $e->getFile(),
                'linha' => $e->getLine(),
            ]
            : null,
    ]);
}
