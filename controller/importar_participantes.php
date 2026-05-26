<?php
ob_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

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

$DEBUG_IMPORTACAO = (
    (isset($_GET['debug']) && (string) $_GET['debug'] === '1') ||
    (isset($_POST['debug']) && (string) $_POST['debug'] === '1')
);

$transacaoAberta = false;
$file = null;

function respond(int $status, array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($status);

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    if ($json === false) {
        http_response_code(500);
        echo '{"status":"erro","success":false,"msg":"Falha ao gerar JSON"}';
        exit;
    }

    echo $json;
    exit;
}

function adicionar_aviso(array &$avisos, string $mensagem): void
{
    if (count($avisos) < 150) {
        $avisos[] = $mensagem;
    }
}

function limpar_bom(string $value): string
{
    return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
}

function garantir_utf8(string $value): string
{
    $value = limpar_bom($value);

    if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
        $convertido = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1');

        if (is_string($convertido)) {
            return $convertido;
        }
    }

    return $value;
}

function remover_acentos(string $value): string
{
    return strtr($value, [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C',
    ]);
}

function chave_coluna(string $value): string
{
    $value = garantir_utf8($value);
    $value = remover_acentos($value);
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

    return trim($value, '_');
}

function normalizar_nome_atividade(string $value): string
{
    $value = garantir_utf8($value);
    $value = remover_acentos($value);
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    return $value;
}

function normalizar_email(string $email): string
{
    $email = trim(garantir_utf8($email));

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($email, 'UTF-8');
    }

    return strtolower($email);
}

function detectar_delimitador(string $linha): string
{
    $linha = garantir_utf8($linha);

    if (preg_match('/^\s*sep=([^\r\n])\s*$/i', trim($linha), $matches)) {
        return $matches[1];
    }

    $delimitadores = [';', ',', "\t"];
    $melhor = ';';
    $maiorQuantidade = 0;

    foreach ($delimitadores as $delimitador) {
        $colunas = str_getcsv($linha, $delimitador);
        $quantidade = count($colunas);

        if ($quantidade > $maiorQuantidade) {
            $maiorQuantidade = $quantidade;
            $melhor = $delimitador;
        }
    }

    return $melhor;
}

function indice_coluna(array $mapa, array $aliases): ?int
{
    foreach ($aliases as $alias) {
        $chave = chave_coluna($alias);

        if (array_key_exists($chave, $mapa)) {
            return $mapa[$chave];
        }
    }

    return null;
}

function valor_coluna(array $linha, ?int $indice): string
{
    if ($indice === null) {
        return '';
    }

    return trim(garantir_utf8((string) ($linha[$indice] ?? '')));
}

function inteiro_ou_null(string $value): ?int
{
    $value = trim(garantir_utf8($value));

    if ($value === '') {
        return null;
    }

    $value = preg_replace('/[^0-9-]/', '', $value) ?? '';

    if ($value === '' || $value === '-') {
        return null;
    }

    return (int) $value;
}

function separar_atividades(string $texto): array
{
    $texto = trim(garantir_utf8($texto));

    if ($texto === '') {
        return [];
    }

    $partes = preg_split('/\s*(?:,|\|)\s*/u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $resultado = [];
    $vistas = [];

    foreach ($partes as $parte) {
        $parte = trim($parte);

        if ($parte === '') {
            continue;
        }

        $chave = normalizar_nome_atividade($parte);

        if ($chave === '' || isset($vistas[$chave])) {
            continue;
        }

        $vistas[$chave] = true;
        $resultado[] = $parte;
    }

    return $resultado;
}

function email_tecnico_importacao(int $eventoId, string $nome, string $atividadesTexto): string
{
    $base = normalizar_nome_atividade($nome);

    if ($base === '') {
        $base = normalizar_nome_atividade($atividadesTexto);
    }

    if ($base === '') {
        $base = bin2hex(random_bytes(6));
    }

    $hash = substr(sha1($eventoId . '|' . $base), 0, 16);

    return 'importado_' . $eventoId . '_' . $hash . '@sicad.local';
}

function senha_para_banco(string $senha): string
{
    $senha = trim(garantir_utf8($senha));

    if ($senha === '') {
        return password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    }

    $info = password_get_info($senha);

    if (($info['algo'] ?? 0) !== 0) {
        return $senha;
    }

    return password_hash($senha, PASSWORD_DEFAULT);
}

function qi(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function tipo_parametro($value): string
{
    if (is_int($value)) {
        return 'i';
    }

    if (is_float($value)) {
        return 'd';
    }

    return 's';
}

function tipo_coluna_para_parametro(array $coluna): string
{
    $tipo = strtolower((string) ($coluna['Type'] ?? ''));

    if (preg_match('/\b(tinyint|smallint|mediumint|int|bigint|year|bit)\b/', $tipo)) {
        return 'i';
    }

    if (preg_match('/\b(float|double|decimal|numeric|real)\b/', $tipo)) {
        return 'd';
    }

    return 's';
}

function bind_parametros(mysqli_stmt $stmt, string $tipos, array &$valores): void
{
    if ($tipos === '') {
        return;
    }

    $refs = [];

    foreach ($valores as $key => &$value) {
        $refs[$key] = &$value;
    }

    if (!$stmt->bind_param($tipos, ...$refs)) {
        throw new RuntimeException('Erro ao vincular parâmetros: ' . $stmt->error);
    }
}

function executar_stmt_sem_resultado(mysqli $conn, string $sql, string $tipos = '', array $valores = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar SQL: ' . $conn->error . ' | SQL: ' . $sql);
    }

    if ($tipos !== '') {
        bind_parametros($stmt, $tipos, $valores);
    }

    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Erro ao executar SQL: ' . $erro . ' | SQL: ' . $sql);
    }

    return $stmt;
}

function selecionar_um_valor(mysqli $conn, string $sql, string $tipos = '', array $valores = [])
{
    $stmt = executar_stmt_sem_resultado($conn, $sql, $tipos, $valores);
    $valor = null;

    if (!$stmt->bind_result($valor)) {
        $erro = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Erro ao ler resultado: ' . $erro);
    }

    $encontrou = $stmt->fetch();
    $stmt->close();

    return $encontrou ? $valor : null;
}

function mapa_tabelas(mysqli $conn): array
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

function resolver_tabela(mysqli $conn, array $candidatos, bool $obrigatoria = true): ?string
{
    $mapa = mapa_tabelas($conn);

    foreach ($candidatos as $candidato) {
        $chave = strtolower($candidato);

        if (isset($mapa[$chave])) {
            return $mapa[$chave];
        }
    }

    if (!$obrigatoria) {
        return null;
    }

    throw new RuntimeException('Tabela não encontrada. Candidatos: ' . implode(', ', $candidatos));
}

function esquema_colunas(mysqli $conn, string $tabela): array
{
    $result = $conn->query('SHOW COLUMNS FROM ' . qi($tabela));

    if (!$result) {
        throw new RuntimeException('Erro ao listar colunas da tabela ' . $tabela . ': ' . $conn->error);
    }

    $colunas = [];
    $porLower = [];
    $porChave = [];

    while ($row = $result->fetch_assoc()) {
        $campo = (string) $row['Field'];
        $colunas[$campo] = $row;
        $porLower[strtolower($campo)] = $campo;
        $porChave[chave_coluna($campo)] = $campo;
    }

    $result->free();

    return [
        'colunas' => $colunas,
        'por_lower' => $porLower,
        'por_chave' => $porChave,
    ];
}

function coluna_existe(array $schema, string $campo): bool
{
    return isset($schema['colunas'][$campo]);
}

function achar_coluna(array $schema, array $aliases, bool $obrigatoria = false, string $rotulo = ''): ?string
{
    foreach ($aliases as $alias) {
        $lower = strtolower($alias);
        $chave = chave_coluna($alias);

        if (isset($schema['por_lower'][$lower])) {
            return $schema['por_lower'][$lower];
        }

        if (isset($schema['por_chave'][$chave])) {
            return $schema['por_chave'][$chave];
        }
    }

    if ($obrigatoria) {
        throw new RuntimeException('Coluna obrigatória não encontrada' . ($rotulo !== '' ? ' (' . $rotulo . ')' : '') . '. Aliases: ' . implode(', ', $aliases));
    }

    return null;
}

function coluna_pk(array $schema, array $preferidas): string
{
    $coluna = achar_coluna($schema, $preferidas, false);

    if ($coluna !== null) {
        return $coluna;
    }

    foreach ($schema['colunas'] as $campo => $info) {
        if (strtoupper((string) ($info['Key'] ?? '')) === 'PRI') {
            return $campo;
        }
    }

    throw new RuntimeException('Não foi possível identificar a chave primária.');
}

function parse_enum_values(string $tipo): array
{
    $tipo = trim($tipo);

    if (stripos($tipo, 'enum(') !== 0 && stripos($tipo, 'set(') !== 0) {
        return [];
    }

    $inicio = strpos($tipo, '(');
    $fim = strrpos($tipo, ')');

    if ($inicio === false || $fim === false || $fim <= $inicio) {
        return [];
    }

    $conteudo = substr($tipo, $inicio + 1, $fim - $inicio - 1);
    $valores = str_getcsv($conteudo, ',', "'", "\\");

    return array_map('strval', $valores);
}

function escolher_enum(string $tipo, array $preferidos): ?string
{
    $valores = parse_enum_values($tipo);

    if (count($valores) === 0) {
        return null;
    }

    $mapa = [];

    foreach ($valores as $valor) {
        $mapa[chave_coluna($valor)] = $valor;
    }

    foreach ($preferidos as $preferido) {
        $chave = chave_coluna($preferido);

        if (isset($mapa[$chave])) {
            return $mapa[$chave];
        }
    }

    return $valores[0];
}

function valor_padrao_coluna(string $campo, array $info)
{
    $tipo = strtolower((string) ($info['Type'] ?? ''));
    $nome = chave_coluna($campo);
    $default = $info['Default'] ?? null;

    if ($default !== null) {
        return $default;
    }

    if (strpos(strtolower((string) ($info['Extra'] ?? '')), 'auto_increment') !== false) {
        return null;
    }

    $enum = escolher_enum($tipo, [
        'participante', 'usuario', 'user', 'ativo', 'ativa', 'confirmado', 'confirmada',
        'presencial', 'sim', 'nao', 'não', 'pendente'
    ]);

    if ($enum !== null) {
        return $enum;
    }

    if (strpos($nome, 'senha') !== false || strpos($nome, 'password') !== false) {
        return password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    }

    if (strpos($nome, 'email') !== false || strpos($nome, 'mail') !== false) {
        return 'importado_' . substr(sha1((string) microtime(true) . random_int(1, 999999)), 0, 20) . '@sicad.local';
    }

    if (preg_match('/\b(tinyint|smallint|mediumint|int|bigint|year|bit)\b/', $tipo)) {
        return 0;
    }

    if (preg_match('/\b(float|double|decimal|numeric|real)\b/', $tipo)) {
        return 0;
    }

    if (strpos($tipo, 'datetime') !== false || strpos($tipo, 'timestamp') !== false) {
        return date('Y-m-d H:i:s');
    }

    if (strpos($tipo, 'date') !== false) {
        return date('Y-m-d');
    }

    if (strpos($tipo, 'time') !== false) {
        return date('H:i:s');
    }

    return '';
}

function inserir_dinamico(mysqli $conn, string $tabela, array $schema, array $valoresInformados): int
{
    $campos = [];
    $valores = [];
    $tipos = '';

    foreach ($valoresInformados as $campo => $valor) {
        if ($campo === null || $campo === '' || !coluna_existe($schema, $campo)) {
            continue;
        }

        if (array_key_exists($campo, $campos)) {
            continue;
        }

        $campos[$campo] = $campo;
        $valores[] = $valor;
        $tipos .= tipo_coluna_para_parametro($schema['colunas'][$campo]);
    }

    foreach ($schema['colunas'] as $campo => $info) {
        if (isset($campos[$campo])) {
            continue;
        }

        $extra = strtolower((string) ($info['Extra'] ?? ''));
        $permiteNull = strtoupper((string) ($info['Null'] ?? '')) === 'YES';
        $temDefault = array_key_exists('Default', $info) && $info['Default'] !== null;
        $autoIncremento = strpos($extra, 'auto_increment') !== false;
        $gerada = strpos($extra, 'generated') !== false;

        if ($autoIncremento || $gerada || $permiteNull || $temDefault) {
            continue;
        }

        $valor = valor_padrao_coluna($campo, $info);

        if ($valor === null) {
            continue;
        }

        $campos[$campo] = $campo;
        $valores[] = $valor;
        $tipos .= tipo_coluna_para_parametro($info);
    }

    if (count($campos) === 0) {
        throw new RuntimeException('Nenhum campo disponível para inserir na tabela ' . $tabela);
    }

    $sql = 'INSERT INTO ' . qi($tabela) . ' (' . implode(', ', array_map('qi', array_values($campos))) . ') VALUES (' . implode(', ', array_fill(0, count($campos), '?')) . ')';
    $stmt = executar_stmt_sem_resultado($conn, $sql, $tipos, $valores);
    $insertId = (int) $stmt->insert_id;
    $stmt->close();

    return $insertId;
}

function adicionar_se_coluna(array &$valores, ?string $coluna, $valor): void
{
    if ($coluna !== null && $coluna !== '') {
        $valores[$coluna] = $valor;
    }
}

function adicionar_status_enum_ou_texto(array &$valores, array $schema, ?string $coluna, array $preferidos, string $fallback): void
{
    if ($coluna === null || !isset($schema['colunas'][$coluna])) {
        return;
    }

    $tipo = (string) ($schema['colunas'][$coluna]['Type'] ?? '');
    $enum = escolher_enum($tipo, $preferidos);
    $valores[$coluna] = $enum !== null ? $enum : $fallback;
}

function obter_primeira_linha_id(mysqli $conn, string $tabela, string $pk): ?int
{
    $sql = 'SELECT ' . qi($pk) . ' FROM ' . qi($tabela) . ' ORDER BY ' . qi($pk) . ' ASC LIMIT 1';
    $valor = selecionar_um_valor($conn, $sql);

    return $valor === null ? null : (int) $valor;
}

function obter_modalidade_id(mysqli $conn, ?string $tabelaModalidade, ?array $schemaModalidade): ?int
{
    if ($tabelaModalidade === null || $schemaModalidade === null) {
        return null;
    }

    $pk = coluna_pk($schemaModalidade, ['codigo', 'id', 'ID', 'modalidade_id']);
    $nomeCol = achar_coluna($schemaModalidade, ['nome', 'descricao', 'modalidade'], false);

    if ($nomeCol !== null) {
        foreach (['presencial', 'Presencial', 'PRESENCIAL'] as $nome) {
            $sql = 'SELECT ' . qi($pk) . ' FROM ' . qi($tabelaModalidade) . ' WHERE ' . qi($nomeCol) . ' = ? ORDER BY ' . qi($pk) . ' ASC LIMIT 1';
            $valor = selecionar_um_valor($conn, $sql, 's', [$nome]);

            if ($valor !== null) {
                return (int) $valor;
            }
        }
    }

    $primeiro = obter_primeira_linha_id($conn, $tabelaModalidade, $pk);

    if ($primeiro !== null) {
        return $primeiro;
    }

    if ($nomeCol === null) {
        return null;
    }

    $valores = [];
    adicionar_se_coluna($valores, $nomeCol, 'presencial');

    $id = inserir_dinamico($conn, $tabelaModalidade, $schemaModalidade, $valores);

    return $id > 0 ? $id : (int) $conn->insert_id;
}

function carregar_atividades_evento(mysqli $conn, string $tabela, string $pk, string $nomeCol, string $eventoFk, int $eventoId): array
{
    $sql = 'SELECT ' . qi($pk) . ', ' . qi($nomeCol) . ' FROM ' . qi($tabela) . ' WHERE ' . qi($eventoFk) . ' = ? ORDER BY ' . qi($pk) . ' ASC';
    $stmt = executar_stmt_sem_resultado($conn, $sql, 'i', [$eventoId]);
    $id = null;
    $nome = null;

    if (!$stmt->bind_result($id, $nome)) {
        $erro = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Erro ao ler atividades: ' . $erro);
    }

    $porNome = [];
    $duplicadas = [];

    while ($stmt->fetch()) {
        $chave = normalizar_nome_atividade((string) $nome);

        if ($chave === '') {
            continue;
        }

        if (!isset($porNome[$chave])) {
            $porNome[$chave] = (int) $id;
        } else {
            $duplicadas[(string) $nome] = true;
        }
    }

    $stmt->close();

    return [$porNome, $duplicadas];
}

function usuario_por_email(mysqli $conn, string $tabela, string $pk, string $emailCol, string $email): ?int
{
    $sql = 'SELECT ' . qi($pk) . ' FROM ' . qi($tabela) . ' WHERE ' . qi($emailCol) . ' = ? ORDER BY ' . qi($pk) . ' ASC LIMIT 1';
    $valor = selecionar_um_valor($conn, $sql, 's', [$email]);

    return $valor === null ? null : (int) $valor;
}

function participacao_existe(mysqli $conn, string $tabela, string $usuarioFk, string $atividadeFk, int $usuarioId, int $atividadeId): bool
{
    $sql = 'SELECT 1 FROM ' . qi($tabela) . ' WHERE ' . qi($usuarioFk) . ' = ? AND ' . qi($atividadeFk) . ' = ? LIMIT 1';
    $valor = selecionar_um_valor($conn, $sql, 'ii', [$usuarioId, $atividadeId]);

    return $valor !== null;
}

function transacao_begin(mysqli $conn): void
{
    if (method_exists($conn, 'begin_transaction')) {
        $conn->begin_transaction();
        return;
    }

    if (!$conn->query('START TRANSACTION')) {
        throw new RuntimeException('Erro ao iniciar transação: ' . $conn->error);
    }
}

try {
    require_once __DIR__ . '/db.php';

    if (ob_get_length()) {
        ob_clean();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, [
            'status' => 'erro',
            'success' => false,
            'msg' => 'Método não permitido'
        ]);
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        respond(500, [
            'status' => 'erro',
            'success' => false,
            'msg' => 'Conexão com o banco não encontrada'
        ]);
    }

    $conn->set_charset('utf8mb4');

    if (!isset($_POST['evento_id']) || !ctype_digit((string) $_POST['evento_id'])) {
        respond(400, [
            'status' => 'erro',
            'success' => false,
            'msg' => 'evento_id não enviado ou inválido'
        ]);
    }

    $eventoId = (int) $_POST['evento_id'];

    if (!isset($_FILES['arquivo'])) {
        respond(400, [
            'status' => 'erro',
            'success' => false,
            'msg' => 'Arquivo não enviado'
        ]);
    }

    if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        respond(400, [
            'status' => 'erro',
            'success' => false,
            'msg' => 'Erro no upload do arquivo',
            'upload_error' => $_FILES['arquivo']['error']
        ]);
    }

    $tmp = $_FILES['arquivo']['tmp_name'] ?? '';

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        respond(400, [
            'status' => 'erro',
            'success' => false,
            'msg' => 'Arquivo inválido'
        ]);
    }

    $file = fopen($tmp, 'r');

    if (!$file) {
        respond(500, [
            'status' => 'erro',
            'success' => false,
            'msg' => 'Erro ao abrir arquivo'
        ]);
    }

    $primeiraLinha = fgets($file);

    if ($primeiraLinha === false) {
        fclose($file);
        $file = null;

        respond(400, [
            'status' => 'erro',
            'success' => false,
            'msg' => 'Arquivo vazio'
        ]);
    }

    $delimitador = detectar_delimitador($primeiraLinha);
    rewind($file);

    $cabecalho = fgetcsv($file, 0, $delimitador);

    if ($cabecalho !== false && isset($cabecalho[0]) && preg_match('/^\s*sep=/i', trim(garantir_utf8((string) $cabecalho[0])))) {
        $cabecalho = fgetcsv($file, 0, $delimitador);
    }

    if ($cabecalho === false || count($cabecalho) === 0) {
        fclose($file);
        $file = null;

        respond(400, [
            'status' => 'erro',
            'success' => false,
            'msg' => 'Cabeçalho não encontrado'
        ]);
    }

    $mapaColunas = [];

    foreach ($cabecalho as $index => $coluna) {
        $mapaColunas[chave_coluna((string) $coluna)] = $index;
    }

    $idxNome = indice_coluna($mapaColunas, ['Nome']);
    $idxSenha = indice_coluna($mapaColunas, ['Senha']);
    $idxEmail = indice_coluna($mapaColunas, ['Email', 'E-mail']);
    $idxTotalAtividades = indice_coluna($mapaColunas, ['Total de Atividades']);
    $idxCargaHorariaTotal = indice_coluna($mapaColunas, ['Carga Horária Total', 'Carga Horaria Total']);
    $idxAtividades = indice_coluna($mapaColunas, ['Atividades Inscritas', 'Atividades']);

    if ($idxNome === null || $idxSenha === null || $idxEmail === null || $idxTotalAtividades === null || $idxCargaHorariaTotal === null || $idxAtividades === null) {
        fclose($file);
        $file = null;

        respond(400, [
            'status' => 'erro',
            'success' => false,
            'msg' => 'CSV inválido. O arquivo precisa ter as colunas Nome, Senha, Email, Total de Atividades, Carga Horária Total e Atividades Inscritas.',
            'colunas_lidas' => array_keys($mapaColunas),
        ]);
    }

    $tEvento = resolver_tabela($conn, ['evento', 'Evento', 'eventos', 'Eventos']);
    $tAtividade = resolver_tabela($conn, ['atividade', 'Atividade', 'atividades', 'Atividades']);
    $tUsuario = resolver_tabela($conn, ['usuario', 'Usuario', 'usuarios', 'Usuarios']);
    $tParticipa = resolver_tabela($conn, ['participa', 'Participa', 'participacao', 'Participacao', 'participações', 'Participacoes']);
    $tModalidade = resolver_tabela($conn, ['modalidade', 'Modalidade', 'modalidades', 'Modalidades'], false);

    $sEvento = esquema_colunas($conn, $tEvento);
    $sAtividade = esquema_colunas($conn, $tAtividade);
    $sUsuario = esquema_colunas($conn, $tUsuario);
    $sParticipa = esquema_colunas($conn, $tParticipa);
    $sModalidade = $tModalidade !== null ? esquema_colunas($conn, $tModalidade) : null;

    $eventoPk = coluna_pk($sEvento, ['codigo', 'id', 'ID', 'evento_id']);

    $atividadePk = coluna_pk($sAtividade, ['ID', 'id', 'codigo', 'atividade_id']);
    $atividadeNomeCol = achar_coluna($sAtividade, ['nome', 'nome_atividade', 'titulo', 'atividade'], true, 'nome da atividade');
    $atividadeEventoFk = achar_coluna($sAtividade, ['fk_Evento_codigo', 'fk_evento_codigo', 'evento_id', 'fk_evento_id', 'codigo_evento'], true, 'FK do evento em atividade');
    $atividadeModalidadeFk = achar_coluna($sAtividade, ['fk_Modalidade_codigo', 'fk_modalidade_codigo', 'modalidade_id', 'fk_modalidade_id', 'codigo_modalidade'], false);

    $usuarioPk = coluna_pk($sUsuario, ['ID', 'id', 'codigo', 'usuario_id']);
    $usuarioNomeCol = achar_coluna($sUsuario, ['nome', 'nome_usuario', 'name'], true, 'nome do usuário');
    $usuarioEmailCol = achar_coluna($sUsuario, ['email', 'e_mail', 'mail'], true, 'email do usuário');
    $usuarioSenhaCol = achar_coluna($sUsuario, ['senha', 'password', 'senha_usuario'], false);

    $participaUsuarioFk = achar_coluna($sParticipa, ['fk_Usuario_ID', 'fk_usuario_id', 'usuario_id', 'fk_user_id', 'user_id'], true, 'FK do usuário em participa');
    $participaAtividadeFk = achar_coluna($sParticipa, ['fk_Atividade_ID', 'fk_atividade_id', 'atividade_id', 'fk_activity_id', 'activity_id'], true, 'FK da atividade em participa');

    $eventoEncontrado = selecionar_um_valor(
        $conn,
        'SELECT ' . qi($eventoPk) . ' FROM ' . qi($tEvento) . ' WHERE ' . qi($eventoPk) . ' = ? LIMIT 1',
        'i',
        [$eventoId]
    );

    if ($eventoEncontrado === null) {
        fclose($file);
        $file = null;

        respond(404, [
            'status' => 'erro',
            'success' => false,
            'msg' => 'Evento não encontrado'
        ]);
    }

    transacao_begin($conn);
    $transacaoAberta = true;

    $modalidadeId = null;

    if ($atividadeModalidadeFk !== null && $tModalidade !== null && $sModalidade !== null) {
        $modalidadeId = obter_modalidade_id($conn, $tModalidade, $sModalidade);
    }

    [$atividadesPorNome, $atividadesDuplicadas] = carregar_atividades_evento(
        $conn,
        $tAtividade,
        $atividadePk,
        $atividadeNomeCol,
        $atividadeEventoFk,
        $eventoId
    );

    $linhasLidas = 0;
    $linhasIgnoradas = 0;
    $usuariosCriados = 0;
    $usuariosExistentes = 0;
    $emailsTecnicosCriados = 0;
    $atividadesCriadas = 0;
    $inscricoesCriadas = 0;
    $inscricoesExistentes = 0;
    $avisos = [];
    $atividadesCriadasNomes = [];

    foreach (array_keys($atividadesDuplicadas) as $nomeDuplicado) {
        adicionar_aviso($avisos, 'Há mais de uma atividade com o nome "' . $nomeDuplicado . '" neste evento. A importação usou a primeira encontrada.');
    }

    while (($data = fgetcsv($file, 0, $delimitador)) !== false) {
        $linhasLidas++;
        $numeroLinhaCsv = $linhasLidas + 1;

        if (count($data) === 1 && trim((string) $data[0]) === '') {
            $linhasIgnoradas++;
            continue;
        }

        $nome = trim(valor_coluna($data, $idxNome));
        $senha = valor_coluna($data, $idxSenha);
        $emailOriginal = normalizar_email(valor_coluna($data, $idxEmail));
        $totalAtividadesCsv = inteiro_ou_null(valor_coluna($data, $idxTotalAtividades));
        $cargaHorariaTotalCsv = inteiro_ou_null(valor_coluna($data, $idxCargaHorariaTotal));
        $atividadesTexto = valor_coluna($data, $idxAtividades);
        $atividadesDaLinha = separar_atividades($atividadesTexto);

        if ($nome === '' && $emailOriginal === '') {
            $linhasIgnoradas++;
            adicionar_aviso($avisos, 'Linha ' . $numeroLinhaCsv . ' ignorada: participante sem nome e sem e-mail.');
            continue;
        }

        if ($nome === '') {
            $nome = $emailOriginal;
        }

        if (count($atividadesDaLinha) === 0) {
            $linhasIgnoradas++;
            adicionar_aviso($avisos, 'Linha ' . $numeroLinhaCsv . ' ignorada: nenhuma atividade informada.');
            continue;
        }

        if ($totalAtividadesCsv !== null && $totalAtividadesCsv !== count($atividadesDaLinha)) {
            adicionar_aviso($avisos, 'Linha ' . $numeroLinhaCsv . ': Total de Atividades informado (' . $totalAtividadesCsv . ') difere da quantidade listada (' . count($atividadesDaLinha) . ').');
        }

        $email = $emailOriginal;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = email_tecnico_importacao($eventoId, $nome, $atividadesTexto);
            $emailsTecnicosCriados++;
            adicionar_aviso($avisos, 'Linha ' . $numeroLinhaCsv . ': e-mail ausente ou inválido. Criado e-mail técnico ' . $email . '.');
        }

        $atividadeIdsParaInserir = [];

        foreach ($atividadesDaLinha as $atividadeNomeCsv) {
            $nomeAtividade = trim($atividadeNomeCsv);
            $chaveAtividade = normalizar_nome_atividade($nomeAtividade);

            if ($chaveAtividade === '') {
                continue;
            }

            if (isset($atividadesPorNome[$chaveAtividade])) {
                $atividadeIdsParaInserir[] = (int) $atividadesPorNome[$chaveAtividade];
                continue;
            }

            $valoresAtividade = [];
            adicionar_se_coluna($valoresAtividade, $atividadeNomeCol, $nomeAtividade);
            adicionar_se_coluna($valoresAtividade, $atividadeEventoFk, $eventoId);

            if ($atividadeModalidadeFk !== null && $modalidadeId !== null) {
                adicionar_se_coluna($valoresAtividade, $atividadeModalidadeFk, $modalidadeId);
            }

            $infoCol = achar_coluna($sAtividade, ['informacoes_atividade', 'informacoes', 'descricao', 'descricao_atividade', 'detalhes'], false);
            $cargaCol = achar_coluna($sAtividade, ['carga_horaria', 'carga', 'horas', 'carga_horaria_total'], false);
            $palestranteCol = achar_coluna($sAtividade, ['palestrante', 'ministrante', 'responsavel'], false);
            $statusCol = achar_coluna($sAtividade, ['status_atividade', 'status'], false);
            $numCol = achar_coluna($sAtividade, ['num_participantes', 'numero_participantes', 'qtd_participantes', 'quantidade_participantes'], false);

            adicionar_se_coluna($valoresAtividade, $infoCol, 'Criada automaticamente pela importação de participantes');
            adicionar_se_coluna($valoresAtividade, $palestranteCol, '');
            adicionar_se_coluna($valoresAtividade, $numCol, 0);

            if ($cargaCol !== null) {
                $cargaAtividade = 0;

                if ($cargaHorariaTotalCsv !== null && count($atividadesDaLinha) > 0) {
                    $cargaAtividade = (int) max(0, round($cargaHorariaTotalCsv / count($atividadesDaLinha)));
                }

                adicionar_se_coluna($valoresAtividade, $cargaCol, $cargaAtividade);
            }

            adicionar_status_enum_ou_texto($valoresAtividade, $sAtividade, $statusCol, ['ativa', 'ativo', 'confirmada', 'confirmado', 'pendente'], 'ativa');

            try {
                $novaAtividadeId = inserir_dinamico($conn, $tAtividade, $sAtividade, $valoresAtividade);

                if ($novaAtividadeId <= 0) {
                    $novaAtividadeId = (int) $conn->insert_id;
                }

                if ($novaAtividadeId <= 0) {
                    adicionar_aviso($avisos, 'Linha ' . $numeroLinhaCsv . ': a atividade "' . $nomeAtividade . '" foi criada, mas o ID não foi retornado.');
                    continue;
                }

                $atividadesCriadas++;
                $atividadesCriadasNomes[$nomeAtividade] = true;
                $atividadesPorNome[$chaveAtividade] = $novaAtividadeId;
                $atividadeIdsParaInserir[] = $novaAtividadeId;
            } catch (Throwable $erroAtividade) {
                adicionar_aviso($avisos, 'Linha ' . $numeroLinhaCsv . ': não foi possível criar a atividade "' . $nomeAtividade . '". Erro: ' . $erroAtividade->getMessage());
                continue;
            }
        }

        $atividadeIdsParaInserir = array_values(array_unique(array_map('intval', $atividadeIdsParaInserir)));

        if (count($atividadeIdsParaInserir) === 0) {
            $linhasIgnoradas++;
            adicionar_aviso($avisos, 'Linha ' . $numeroLinhaCsv . ' ignorada: não foi possível localizar ou criar as atividades informadas.');
            continue;
        }

        $usuarioId = usuario_por_email($conn, $tUsuario, $usuarioPk, $usuarioEmailCol, $email);

        if ($usuarioId !== null) {
            $usuariosExistentes++;
        } else {
            $valoresUsuario = [];
            adicionar_se_coluna($valoresUsuario, $usuarioNomeCol, $nome);
            adicionar_se_coluna($valoresUsuario, $usuarioEmailCol, $email);

            if ($usuarioSenhaCol !== null) {
                adicionar_se_coluna($valoresUsuario, $usuarioSenhaCol, senha_para_banco($senha));
            }

            $tipoCol = achar_coluna($sUsuario, ['tipo', 'tipo_usuario', 'perfil', 'role', 'papel'], false);
            $statusUsuarioCol = achar_coluna($sUsuario, ['status', 'status_usuario', 'ativo'], false);

            adicionar_status_enum_ou_texto($valoresUsuario, $sUsuario, $tipoCol, ['participante', 'usuario', 'user'], 'participante');
            adicionar_status_enum_ou_texto($valoresUsuario, $sUsuario, $statusUsuarioCol, ['ativo', 'ativa', 'confirmado', 'confirmada', 'pendente'], 'ativo');

            try {
                $usuarioId = inserir_dinamico($conn, $tUsuario, $sUsuario, $valoresUsuario);

                if ($usuarioId <= 0) {
                    $usuarioId = (int) $conn->insert_id;
                }

                if ($usuarioId <= 0) {
                    $linhasIgnoradas++;
                    adicionar_aviso($avisos, 'Linha ' . $numeroLinhaCsv . ': o participante "' . $nome . '" foi criado, mas o ID não foi retornado.');
                    continue;
                }

                $usuariosCriados++;
            } catch (Throwable $erroUsuario) {
                $linhasIgnoradas++;
                adicionar_aviso($avisos, 'Linha ' . $numeroLinhaCsv . ': não foi possível criar o participante "' . $nome . '". Erro: ' . $erroUsuario->getMessage());
                continue;
            }
        }

        foreach ($atividadeIdsParaInserir as $atividadeId) {
            if (participacao_existe($conn, $tParticipa, $participaUsuarioFk, $participaAtividadeFk, $usuarioId, $atividadeId)) {
                $inscricoesExistentes++;
                continue;
            }

            $valoresParticipa = [];
            adicionar_se_coluna($valoresParticipa, $participaUsuarioFk, $usuarioId);
            adicionar_se_coluna($valoresParticipa, $participaAtividadeFk, $atividadeId);

            $statusParticipaCol = achar_coluna($sParticipa, ['status', 'status_participacao', 'situacao'], false);
            adicionar_status_enum_ou_texto($valoresParticipa, $sParticipa, $statusParticipaCol, ['confirmado', 'confirmada', 'ativo', 'ativa', 'inscrito', 'pendente'], 'confirmado');

            try {
                inserir_dinamico($conn, $tParticipa, $sParticipa, $valoresParticipa);
                $inscricoesCriadas++;
            } catch (Throwable $erroParticipa) {
                adicionar_aviso($avisos, 'Linha ' . $numeroLinhaCsv . ': não foi possível vincular participante ID ' . $usuarioId . ' à atividade ID ' . $atividadeId . '. Erro: ' . $erroParticipa->getMessage());
                continue;
            }
        }
    }

    if ($transacaoAberta) {
        $conn->commit();
        $transacaoAberta = false;
    }

    if (is_resource($file)) {
        fclose($file);
        $file = null;
    }

    $statusResposta = ($linhasLidas > 0 && $inscricoesCriadas === 0 && $inscricoesExistentes === 0) ? 422 : 200;

    respond($statusResposta, [
        'status' => $statusResposta === 200 ? 'ok' : 'erro',
        'success' => $statusResposta === 200,
        'msg' => $statusResposta === 200 ? 'Importação concluída' : 'Nenhuma inscrição foi criada ou encontrada. Verifique os avisos.',
        'resumo' => [
            'linhas_lidas' => $linhasLidas,
            'linhas_ignoradas' => $linhasIgnoradas,
            'usuarios_criados' => $usuariosCriados,
            'usuarios_existentes' => $usuariosExistentes,
            'emails_tecnicos_criados' => $emailsTecnicosCriados,
            'atividades_criadas' => $atividadesCriadas,
            'atividades_criadas_nomes' => array_keys($atividadesCriadasNomes),
            'inscricoes_criadas' => $inscricoesCriadas,
            'inscricoes_existentes' => $inscricoesExistentes,
            'avisos' => $avisos,
        ],
    ]);
} catch (Throwable $e) {
    if ($transacaoAberta && isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log('Erro no rollback da importação: ' . $rollbackError->getMessage());
        }
    }

    if (isset($file) && is_resource($file)) {
        fclose($file);
    }

    error_log('importar_participantes.php: ' . $e->getMessage());

    $payload = [
        'status' => 'erro',
        'success' => false,
        'msg' => 'Erro interno ao importar participantes'
    ];

    if (isset($DEBUG_IMPORTACAO) && $DEBUG_IMPORTACAO) {
        $payload['debug'] = [
            'erro' => $e->getMessage(),
            'arquivo' => $e->getFile(),
            'linha' => $e->getLine(),
        ];
    }

    respond(500, $payload);
}
