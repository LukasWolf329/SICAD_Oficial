<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    
    require_once __DIR__ . "/db.php";

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException("Conexão inválida. O arquivo de conexão precisa criar a variável \$conn como mysqli.");
    }

    $conn->set_charset("utf8mb4");

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        responderErro("Método não permitido. Use POST.", 405);
    }

    if (!isset($_FILES["arquivo_json"])) {
        throw new RuntimeException("Arquivo JSON não enviado. Use o campo 'arquivo_json'.");
    }

    $arquivo = $_FILES["arquivo_json"];

    validarUploadJson($arquivo);

    $conteudo = file_get_contents($arquivo["tmp_name"]);

    if ($conteudo === false || trim($conteudo) === "") {
        throw new RuntimeException("O arquivo JSON está vazio ou não pôde ser lido.");
    }

    $payload = json_decode($conteudo, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("JSON inválido: " . json_last_error_msg());
    }

    $eventos = extrairEventosDoPayload($payload);

    if (count($eventos) === 0) {
        throw new RuntimeException("Nenhum evento encontrado no arquivo JSON.");
    }

    $usuarioImportadorId = isset($_POST["userId"]) && $_POST["userId"] !== ""
        ? (int) $_POST["userId"]
        : null;

    $conn->begin_transaction();

    $resultado = [
        "arquivo" => $arquivo["name"] ?? "eventos.json",
        "eventos_criados" => [],
        "total_eventos" => 0,
        "total_atividades" => 0,
        "total_participantes_vinculados" => 0,
        "total_organizadores_vinculados" => 0,
    ];

    foreach ($eventos as $indiceEvento => $eventoOriginal) {
        if (!is_array($eventoOriginal)) {
            throw new RuntimeException("Evento {$indiceEvento}: precisa ser um objeto JSON.");
        }

        $evento = normalizarEvento($eventoOriginal, (int) $indiceEvento);

        $eventoId = inserirEvento($conn, $evento);

        $resultado["eventos_criados"][] = [
            "evento_id" => $eventoId,
            "nome" => $evento["nome"],
        ];

        $resultado["total_eventos"]++;

        if (
            $usuarioImportadorId !== null &&
            $usuarioImportadorId > 0 &&
            usuarioExistePorId($conn, $usuarioImportadorId)
        ) {
            vincularUsuarioAoTipo($conn, $usuarioImportadorId, "organizador");
            vincularGerencia($conn, $usuarioImportadorId, $eventoId);

            $resultado["total_organizadores_vinculados"]++;
        }

        if (
            !empty($evento["responsavel_evento"]) &&
            filter_var($evento["responsavel_evento"], FILTER_VALIDATE_EMAIL)
        ) {
            $responsavel = [
                "nome" => $evento["responsavel_evento"],
                "email" => $evento["responsavel_evento"],
            ];

            $responsavelId = buscarOuCriarUsuario($conn, $responsavel);

            vincularUsuarioAoTipo($conn, $responsavelId, "organizador");
            vincularGerencia($conn, $responsavelId, $eventoId);

            $resultado["total_organizadores_vinculados"]++;
        }

        $organizadores = normalizarListaPessoas($eventoOriginal["organizadores"] ?? []);

        foreach ($organizadores as $organizador) {
            validarPessoa($organizador, "organizador do evento {$indiceEvento}");

            $organizadorId = buscarOuCriarUsuario($conn, $organizador);

            vincularUsuarioAoTipo($conn, $organizadorId, "organizador");
            vincularGerencia($conn, $organizadorId, $eventoId);

            $resultado["total_organizadores_vinculados"]++;
        }

        foreach ($evento["atividades"] as $indiceAtividade => $atividadeOriginal) {
            if (!is_array($atividadeOriginal)) {
                throw new RuntimeException("Evento {$indiceEvento}, atividade {$indiceAtividade}: precisa ser um objeto JSON.");
            }

            $atividade = normalizarAtividade(
                $atividadeOriginal,
                $evento,
                (int) $indiceEvento,
                (int) $indiceAtividade
            );

            $modalidadeId = buscarOuCriarModalidade($conn, $atividade["modalidade"]);

            $atividadeId = inserirAtividade(
                $conn,
                $eventoId,
                $modalidadeId,
                $atividade
            );

            $resultado["total_atividades"]++;

            foreach ($atividade["participantes"] as $indiceParticipante => $participante) {
                validarPessoa(
                    $participante,
                    "participante do evento {$indiceEvento}, atividade {$indiceAtividade}, índice {$indiceParticipante}"
                );

                $usuarioId = buscarOuCriarUsuario($conn, $participante);

                vincularUsuarioAoTipo($conn, $usuarioId, "participante");
                vincularParticipanteNaAtividade($conn, $usuarioId, $atividadeId);

                $resultado["total_participantes_vinculados"]++;
            }

            atualizarNumeroParticipantesAtividade($conn, $atividadeId);
        }
    }

    $conn->commit();

    responder([
        "ok" => true,
        "mensagem" => "Arquivo JSON importado com sucesso.",
        "resultado" => $resultado,
    ]);

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // Ignora erro de rollback para preservar o erro original.
        }
    }

    responderErro($e->getMessage(), 400);
}

/* -------------------------------------------------------------------------- */
/* Resposta JSON                                                               */
/* -------------------------------------------------------------------------- */

function responder(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function responderErro(string $mensagem, int $status = 400): void
{
    responder([
        "ok" => false,
        "erro" => $mensagem,
    ], $status);
}

/* -------------------------------------------------------------------------- */
/* Helpers mysqli                                                              */
/* -------------------------------------------------------------------------- */

function executar(mysqli $conn, string $sql, string $types = "", array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);

    if ($types !== "") {
        if (strlen($types) !== count($params)) {
            throw new RuntimeException("Quantidade de tipos diferente da quantidade de parâmetros no SQL.");
        }

        $refs = [$types];

        foreach ($params as $key => &$value) {
            $refs[] = &$value;
        }

        $stmt->bind_param(...$refs);
    }

    $stmt->execute();

    return $stmt;
}

function buscarUm(mysqli $conn, string $sql, string $types = "", array $params = []): ?array
{
    $stmt = executar($conn, $sql, $types, $params);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/* -------------------------------------------------------------------------- */
/* Upload / JSON                                                               */
/* -------------------------------------------------------------------------- */

function validarUploadJson(array $arquivo): void
{
    if (!isset($arquivo["error"]) || is_array($arquivo["error"])) {
        throw new RuntimeException("Upload inválido.");
    }

    if ($arquivo["error"] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Erro no upload do arquivo. Código: " . $arquivo["error"]);
    }

    $limiteBytes = 5 * 1024 * 1024;

    if ((int) $arquivo["size"] > $limiteBytes) {
        throw new RuntimeException("O arquivo JSON deve ter no máximo 5 MB.");
    }

    $nome = $arquivo["name"] ?? "";
    $extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));

    if ($extensao !== "json") {
        throw new RuntimeException("O arquivo precisa ter extensão .json.");
    }
}

function extrairEventosDoPayload($payload): array
{
    if (!is_array($payload)) {
        throw new RuntimeException("O JSON precisa ser um objeto ou uma lista.");
    }

    if (array_key_exists("eventos", $payload)) {
        if (!is_array($payload["eventos"])) {
            throw new RuntimeException("O campo 'eventos' precisa ser uma lista.");
        }

        return normalizarListaGenerica($payload["eventos"]);
    }

    if (arrayEhLista($payload)) {
        return $payload;
    }

    throw new RuntimeException("Formato inválido. Use { \"eventos\": [...] } ou uma lista direta de eventos.");
}

function arrayEhLista(array $array): bool
{
    return $array === [] || array_keys($array) === range(0, count($array) - 1);
}

function normalizarListaGenerica(array $valor): array
{
    return arrayEhLista($valor) ? $valor : array_values($valor);
}

function normalizarListaPessoas($valor): array
{
    if ($valor === null || $valor === "") {
        return [];
    }

    if (!is_array($valor)) {
        throw new RuntimeException("Lista de pessoas inválida.");
    }

    if (arrayEhLista($valor)) {
        return $valor;
    }

    $saida = [];

    foreach ($valor as $nome => $dados) {
        if (is_array($dados)) {
            $item = $dados;
            $item["nome"] = $item["nome"] ?? (string) $nome;
            $saida[] = $item;
            continue;
        }

        if (is_string($dados)) {
            $saida[] = [
                "nome" => (string) $nome,
                "email" => $dados,
            ];
        }
    }

    return $saida;
}

/* -------------------------------------------------------------------------- */
/* Normalização / validação                                                    */
/* -------------------------------------------------------------------------- */

function normalizarEvento(array $evento, int $indiceEvento): array
{
    if (empty($evento["nome"])) {
        throw new RuntimeException("Evento {$indiceEvento}: campo 'nome' é obrigatório.");
    }

    $dataInicio = normalizarData(
        $evento["data_inicio"] ?? $evento["data"] ?? null,
        "data_inicio/data do evento {$indiceEvento}"
    );

    $dataFim = !empty($evento["data_fim"])
        ? normalizarData($evento["data_fim"], "data_fim do evento {$indiceEvento}")
        : $dataInicio;

    $atividades = $evento["atividades"] ?? [];

    if (!is_array($atividades)) {
        throw new RuntimeException("Evento {$indiceEvento}: campo 'atividades' precisa ser uma lista.");
    }

    $responsavel = $evento["responsavel_evento"]
        ?? $evento["responsavel"]
        ?? $evento["responsável"]
        ?? null;

    $categoria = $evento["categoria"] ?? $evento["modalidade"] ?? "presencial";

    return [
        "nome" => trim((string) $evento["nome"]),
        "descricao" => $evento["descricao"] ?? null,
        "data_inicio" => $dataInicio,
        "data_fim" => $dataFim,
        "responsavel_evento" => $responsavel !== null ? trim((string) $responsavel) : null,
        "categoria" => normalizarModalidade((string) $categoria),
        "atividades" => normalizarListaGenerica($atividades),
    ];
}

function normalizarAtividade(
    array $atividade,
    array $evento,
    int $indiceEvento,
    int $indiceAtividade
): array {
    if (empty($atividade["nome"])) {
        throw new RuntimeException("Evento {$indiceEvento}, atividade {$indiceAtividade}: campo 'nome' é obrigatório.");
    }

    $participantes = normalizarListaPessoas($atividade["participantes"] ?? []);
    $modalidade = $atividade["modalidade"] ?? $evento["categoria"] ?? "presencial";

    $cargaHoraria = null;

    if (isset($atividade["carga_horaria"]) && $atividade["carga_horaria"] !== "") {
        $cargaHoraria = (int) $atividade["carga_horaria"];
    }

    return [
        "nome" => trim((string) $atividade["nome"]),
        "data" => !empty($atividade["data"])
            ? normalizarData($atividade["data"], "data da atividade {$indiceAtividade} do evento {$indiceEvento}")
            : null,
        "horario" => $atividade["horario"] ?? $atividade["horário"] ?? null,
        "horario_inicio" => $atividade["horario_inicio"] ?? $atividade["horário_inicio"] ?? null,
        "horario_fim" => $atividade["horario_fim"] ?? $atividade["horário_fim"] ?? null,
        "informacoes_atividade" => $atividade["informacoes_atividade"] ?? $atividade["descricao"] ?? null,
        "carga_horaria" => $cargaHoraria,
        "palestrante" => $atividade["palestrante"] ?? null,
        "status_atividade" => normalizarStatusAtividade($atividade["status_atividade"] ?? "confirmada"),
        "modalidade" => normalizarModalidade((string) $modalidade),
        "participantes" => $participantes,
    ];
}

function normalizarData($valor, string $campo): string
{
    if ($valor === null || trim((string) $valor) === "") {
        throw new RuntimeException("Campo de data obrigatório: {$campo}.");
    }

    $valor = trim((string) $valor);
    $formatos = ["Y-m-d", "d/m/Y", "d-m-Y"];

    foreach ($formatos as $formato) {
        $dt = DateTime::createFromFormat($formato, $valor);

        if ($dt && $dt->format($formato) === $valor) {
            return $dt->format("Y-m-d");
        }
    }

    throw new RuntimeException("Data inválida em {$campo}: {$valor}. Use YYYY-MM-DD ou DD/MM/YYYY.");
}

function normalizarModalidade(string $modalidade): string
{
    $valor = trim($modalidade);
    $valor = function_exists("mb_strtolower")
        ? mb_strtolower($valor, "UTF-8")
        : strtolower($valor);

    $valor = strtr($valor, [
        "á" => "a",
        "à" => "a",
        "ã" => "a",
        "â" => "a",
        "é" => "e",
        "ê" => "e",
        "í" => "i",
        "ó" => "o",
        "ô" => "o",
        "õ" => "o",
        "ú" => "u",
        "ç" => "c",
    ]);

    if ($valor === "remoto") {
        $valor = "online";
    }

    if ($valor === "hybrid") {
        $valor = "hibrido";
    }

    $permitidas = ["presencial", "online", "hibrido"];

    if (!in_array($valor, $permitidas, true)) {
        throw new RuntimeException("Modalidade inválida: {$modalidade}. Use presencial, online ou hibrido.");
    }

    return $valor;
}

function normalizarStatusAtividade(string $status): string
{
    $valor = trim($status);
    $valor = function_exists("mb_strtolower")
        ? mb_strtolower($valor, "UTF-8")
        : strtolower($valor);

    $permitidos = ["confirmada", "cancelada", "realizada"];

    return in_array($valor, $permitidos, true) ? $valor : "confirmada";
}

function validarPessoa(array $pessoa, string $contexto): void
{
    if (empty($pessoa["nome"])) {
        throw new RuntimeException("Pessoa sem nome em {$contexto}.");
    }

    if (empty($pessoa["email"])) {
        throw new RuntimeException("Pessoa '{$pessoa["nome"]}' sem email em {$contexto}.");
    }

    if (!filter_var($pessoa["email"], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("Email inválido em {$contexto}: {$pessoa["email"]}.");
    }
}

function montarInformacoesAtividade(array $atividade): ?string
{
    $partes = [];

    if (!empty($atividade["informacoes_atividade"])) {
        $partes[] = trim((string) $atividade["informacoes_atividade"]);
    }

    if (!empty($atividade["data"])) {
        $partes[] = "Data da atividade: " . $atividade["data"];
    }

    if (!empty($atividade["horario"])) {
        $partes[] = "Horário: " . $atividade["horario"];
    }

    if (!empty($atividade["horario_inicio"])) {
        $partes[] = "Horário de início: " . $atividade["horario_inicio"];
    }

    if (!empty($atividade["horario_fim"])) {
        $partes[] = "Horário de fim: " . $atividade["horario_fim"];
    }

    return count($partes) > 0 ? implode("\n", $partes) : null;
}

/* -------------------------------------------------------------------------- */
/* Inserts principais                                                          */
/* -------------------------------------------------------------------------- */

function inserirEvento(mysqli $conn, array $evento): int
{
    $stmt = executar(
        $conn,
        "INSERT INTO evento (descricao, nome, data_inicio, data_fim, responsavel_evento)
         VALUES (?, ?, ?, ?, ?)",
        "sssss",
        [
            $evento["descricao"],
            $evento["nome"],
            $evento["data_inicio"],
            $evento["data_fim"],
            $evento["responsavel_evento"],
        ]
    );

    $stmt->close();

    return (int) $conn->insert_id;
}

function inserirAtividade(mysqli $conn, int $eventoId, int $modalidadeId, array $atividade): int
{
    $informacoes = montarInformacoesAtividade($atividade);
    $numParticipantes = count($atividade["participantes"] ?? []);

    $stmt = executar(
        $conn,
        "INSERT INTO atividade
            (
                informacoes_atividade,
                carga_horaria,
                nome,
                palestrante,
                status_atividade,
                num_participantes,
                fk_evento_codigo,
                fk_modalidade_codigo
            )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        "sisssiii",
        [
            $informacoes,
            $atividade["carga_horaria"],
            $atividade["nome"],
            $atividade["palestrante"],
            $atividade["status_atividade"],
            $numParticipantes,
            $eventoId,
            $modalidadeId,
        ]
    );

    $stmt->close();

    return (int) $conn->insert_id;
}

/* -------------------------------------------------------------------------- */
/* Usuário                                                                     */
/* -------------------------------------------------------------------------- */

function usuarioExistePorId(mysqli $conn, int $usuarioId): bool
{
    $row = buscarUm(
        $conn,
        "SELECT 1 AS existe FROM usuario WHERE id = ? LIMIT 1",
        "i",
        [$usuarioId]
    );

    return $row !== null;
}

function buscarOuCriarUsuario(mysqli $conn, array $pessoa): int
{
    $nome = trim((string) $pessoa["nome"]);
    $email = strtolower(trim((string) $pessoa["email"]));

    $row = buscarUm(
        $conn,
        "SELECT id AS id FROM usuario WHERE LOWER(email) = ? LIMIT 1",
        "s",
        [$email]
    );

    if ($row !== null) {
        return (int) $row["id"];
    }

    $senhaTemporaria = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $cpf = $pessoa["cpf"] ?? null;
    $telefone = $pessoa["telefone"] ?? null;

    $stmt = executar(
        $conn,
        "INSERT INTO usuario (nome, email, senha, cpf, telefone)
         VALUES (?, ?, ?, ?, ?)",
        "sssss",
        [$nome, $email, $senhaTemporaria, $cpf, $telefone]
    );

    $stmt->close();

    return (int) $conn->insert_id;
}

/* -------------------------------------------------------------------------- */
/* Modalidade                                                                  */
/* -------------------------------------------------------------------------- */

function buscarOuCriarModalidade(mysqli $conn, string $modalidade): int
{
    $row = buscarUm(
        $conn,
        "SELECT codigo FROM modalidade WHERE nome = ? LIMIT 1",
        "s",
        [$modalidade]
    );

    if ($row !== null) {
        return (int) $row["codigo"];
    }

    $stmt = executar(
        $conn,
        "INSERT INTO modalidade (nome) VALUES (?)",
        "s",
        [$modalidade]
    );

    $stmt->close();

    return (int) $conn->insert_id;
}

/* -------------------------------------------------------------------------- */
/* Tipo / e_do                                                                 */
/* -------------------------------------------------------------------------- */

function buscarOuCriarTipo(mysqli $conn, string $tipo): int
{
    $permitidos = ["participante", "organizador", "administrador_site"];

    if (!in_array($tipo, $permitidos, true)) {
        throw new RuntimeException("Tipo de usuário inválido: {$tipo}.");
    }

    $row = buscarUm(
        $conn,
        "SELECT codigo FROM tipo WHERE descricao = ? LIMIT 1",
        "s",
        [$tipo]
    );

    if ($row !== null) {
        return (int) $row["codigo"];
    }

    $stmt = executar(
        $conn,
        "INSERT INTO tipo (descricao) VALUES (?)",
        "s",
        [$tipo]
    );

    $stmt->close();

    return (int) $conn->insert_id;
}

function vincularUsuarioAoTipo(mysqli $conn, int $usuarioId, string $tipo): void
{
    $tipoId = buscarOuCriarTipo($conn, $tipo);

    $row = buscarUm(
        $conn,
        "SELECT 1 AS existe
         FROM e_do
         WHERE fk_tipo_codigo = ?
           AND fk_usuario_id = ?
         LIMIT 1",
        "ii",
        [$tipoId, $usuarioId]
    );

    if ($row !== null) {
        return;
    }

    $stmt = executar(
        $conn,
        "INSERT INTO e_do (fk_tipo_codigo, fk_usuario_id)
         VALUES (?, ?)",
        "ii",
        [$tipoId, $usuarioId]
    );

    $stmt->close();
}

/* -------------------------------------------------------------------------- */
/* Gerencia / Participa                                                        */
/* -------------------------------------------------------------------------- */

function vincularGerencia(mysqli $conn, int $usuarioId, int $eventoId): void
{
    $row = buscarUm(
        $conn,
        "SELECT 1 AS existe
         FROM gerencia
         WHERE fk_usuario_id = ?
           AND fk_evento_codigo = ?
         LIMIT 1",
        "ii",
        [$usuarioId, $eventoId]
    );

    if ($row !== null) {
        return;
    }

    $stmt = executar(
        $conn,
        "INSERT INTO gerencia (fk_usuario_id, fk_evento_codigo)
         VALUES (?, ?)",
        "ii",
        [$usuarioId, $eventoId]
    );

    $stmt->close();
}

function vincularParticipanteNaAtividade(mysqli $conn, int $usuarioId, int $atividadeId): void
{
    $row = buscarUm(
        $conn,
        "SELECT 1 AS existe
         FROM participa
         WHERE fk_usuario_id = ?
           AND fk_atividade_id = ?
         LIMIT 1",
        "ii",
        [$usuarioId, $atividadeId]
    );

    if ($row !== null) {
        return;
    }

    $stmt = executar(
        $conn,
        "INSERT INTO participa (fk_usuario_id, fk_atividade_id)
         VALUES (?, ?)",
        "ii",
        [$usuarioId, $atividadeId]
    );

    $stmt->close();
}

function atualizarNumeroParticipantesAtividade(mysqli $conn, int $atividadeId): void
{
    $stmt = executar(
        $conn,
        "UPDATE atividade
         SET num_participantes = (
            SELECT COUNT(*)
            FROM participa
            WHERE fk_atividade_id = ?
         )
         WHERE id = ?",
        "ii",
        [$atividadeId, $atividadeId]
    );

    $stmt->close();
}