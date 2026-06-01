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
        throw new RuntimeException("Conexão inválida. O arquivo db.php precisa criar a variável \$conn como mysqli.");
    }

    $conn->set_charset("utf8mb4");

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        responderErro("Método não permitido. Use POST.", 405);
    }

    $entrada = lerEntradaJson();
    $payload = $entrada["payload"];

    $extracao = extrairEventosDoPayload($payload);
    $eventos = $extracao["eventos"];
    $instituicao = normalizarInstituicao($extracao["instituicao"] ?? null);

    if (count($eventos) === 0) {
        throw new RuntimeException("Nenhum evento encontrado no JSON.");
    }

    $usuarioImportadorId = isset($_POST["userId"]) && $_POST["userId"] !== ""
        ? (int) $_POST["userId"]
        : null;

    $conn->begin_transaction();

    $resultado = [
        "ok" => true,
        "origem" => $entrada["origem"],
        "arquivo" => $entrada["arquivo"],
        "instituicao" => $instituicao,
        "eventos_criados" => [],
        "total_eventos" => 0,
        "total_atividades" => 0,
        "total_inscritos_evento_importados" => 0,
        "total_participantes_vinculados" => 0,
        "total_organizadores_vinculados" => 0,
        "total_certificados_emitidos" => 0,
        "total_certificados_ja_existentes" => 0,
        "total_certificados_nao_emitidos_por_ausencia" => 0,
        "avisos" => [],
    ];

    foreach ($eventos as $indiceEvento => $eventoOriginal) {
        if (!is_array($eventoOriginal)) {
            throw new RuntimeException("Evento {$indiceEvento}: precisa ser um objeto JSON.");
        }

        $evento = normalizarEvento($eventoOriginal, (int) $indiceEvento, $instituicao);
        $eventoId = inserirEvento($conn, $evento);

        $eventoResumo = [
            "evento_id" => $eventoId,
            "nome" => $evento["nome"],
            "atividades" => 0,
            "inscritos_evento" => 0,
            "participantes_vinculados" => 0,
            "certificados_emitidos" => 0,
            "certificados_ja_existentes" => 0,
            "certificados_nao_emitidos_por_ausencia" => 0,
        ];

        $resultado["total_eventos"]++;

        if (
            $usuarioImportadorId !== null &&
            $usuarioImportadorId > 0 &&
            usuarioExistePorId($conn, $usuarioImportadorId)
        ) {
            vincularUsuarioAoTipo($conn, $usuarioImportadorId, "organizador");

            if (vincularGerencia($conn, $usuarioImportadorId, $eventoId)) {
                $resultado["total_organizadores_vinculados"]++;
            }
        }

        $pessoaInstituicao = pessoaDaInstituicao($instituicao);

        if ($pessoaInstituicao !== null) {
            $instituicaoUsuarioId = buscarOuCriarUsuario($conn, $pessoaInstituicao);
            vincularUsuarioAoTipo($conn, $instituicaoUsuarioId, "organizador");

            if (vincularGerencia($conn, $instituicaoUsuarioId, $eventoId)) {
                $resultado["total_organizadores_vinculados"]++;
            }
        }

        if (!empty($evento["responsavel_evento"]) && filter_var($evento["responsavel_evento"], FILTER_VALIDATE_EMAIL)) {
            $responsavel = [
                "nome" => $evento["responsavel_nome"] ?: $evento["responsavel_evento"],
                "email" => $evento["responsavel_evento"],
            ];

            $responsavelId = buscarOuCriarUsuario($conn, $responsavel);
            vincularUsuarioAoTipo($conn, $responsavelId, "organizador");

            if (vincularGerencia($conn, $responsavelId, $eventoId)) {
                $resultado["total_organizadores_vinculados"]++;
            }
        }

        $organizadores = normalizarListaPessoas($eventoOriginal["organizadores"] ?? []);

        foreach ($organizadores as $indiceOrganizador => $organizador) {
            validarPessoa($organizador, "organizador do evento {$indiceEvento}, índice {$indiceOrganizador}");

            $organizadorId = buscarOuCriarUsuario($conn, $organizador);
            vincularUsuarioAoTipo($conn, $organizadorId, "organizador");

            if (vincularGerencia($conn, $organizadorId, $eventoId)) {
                $resultado["total_organizadores_vinculados"]++;
            }
        }

        $inscritosEvento = normalizarListaPessoas($eventoOriginal["inscritos"] ?? []);

        foreach ($inscritosEvento as $indiceInscrito => $inscrito) {
            validarPessoa($inscrito, "inscrito do evento {$indiceEvento}, índice {$indiceInscrito}");

            $usuarioId = buscarOuCriarUsuario($conn, $inscrito);
            vincularUsuarioAoTipo($conn, $usuarioId, "participante");

            $resultado["total_inscritos_evento_importados"]++;
            $eventoResumo["inscritos_evento"]++;
        }

        if (count($inscritosEvento) > 0) {
            adicionarAvisoUnico(
                $resultado["avisos"],
                "Inscritos no nível do evento foram criados/atualizados em usuario e vinculados ao tipo participante em e_do. O schema informado não possui tabela de inscrição direta em evento; por isso, certificado é emitido apenas por atividade."
            );
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
            $atividadeId = inserirAtividade($conn, $eventoId, $modalidadeId, $atividade);

            $resultado["total_atividades"]++;
            $eventoResumo["atividades"]++;

            foreach ($atividade["participantes"] as $indiceParticipante => $participante) {
                validarPessoa(
                    $participante,
                    "participante da atividade {$indiceAtividade} do evento {$indiceEvento}, índice {$indiceParticipante}"
                );

                $usuarioId = buscarOuCriarUsuario($conn, $participante);
                vincularUsuarioAoTipo($conn, $usuarioId, "participante");

                if (vincularParticipanteNaAtividade($conn, $usuarioId, $atividadeId)) {
                    $resultado["total_participantes_vinculados"]++;
                    $eventoResumo["participantes_vinculados"]++;
                }

                if (participanteEstaPresente($participante)) {
                    $certificadoCriado = inserirCertificadoSeNaoExistir(
                        $conn,
                        $usuarioId,
                        $atividadeId,
                        $participante,
                        $atividade,
                        $evento
                    );

                    if ($certificadoCriado) {
                        $resultado["total_certificados_emitidos"]++;
                        $eventoResumo["certificados_emitidos"]++;
                    } else {
                        $resultado["total_certificados_ja_existentes"]++;
                        $eventoResumo["certificados_ja_existentes"]++;
                    }
                } else {
                    $resultado["total_certificados_nao_emitidos_por_ausencia"]++;
                    $eventoResumo["certificados_nao_emitidos_por_ausencia"]++;
                }
            }

            atualizarNumeroParticipantesAtividade($conn, $atividadeId);
        }

        $resultado["eventos_criados"][] = $eventoResumo;
    }

    $conn->commit();

    responder([
        "ok" => true,
        "mensagem" => "JSON importado com sucesso. Certificados emitidos somente para inscritos de atividade com presente=true.",
        "resultado" => $resultado,
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // Mantém o erro original.
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
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function responderErro(string $mensagem, int $status = 400): void
{
    responder([
        "ok" => false,
        "erro" => $mensagem,
    ], $status);
}

function adicionarAvisoUnico(array &$avisos, string $mensagem): void
{
    if (!in_array($mensagem, $avisos, true)) {
        $avisos[] = $mensagem;
    }
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

function colunaExiste(mysqli $conn, string $tabela, string $coluna): bool
{
    $row = buscarUm(
        $conn,
        "SELECT 1 AS existe
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1",
        "ss",
        [$tabela, $coluna]
    );

    return $row !== null;
}

/* -------------------------------------------------------------------------- */
/* Entrada / JSON                                                              */
/* -------------------------------------------------------------------------- */

function lerEntradaJson(): array
{
    $conteudo = null;
    $origem = null;
    $arquivo = null;

    if (isset($_FILES["arquivo_json"]) && ($_FILES["arquivo_json"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload = $_FILES["arquivo_json"];
        validarUploadJson($upload);

        $conteudo = file_get_contents($upload["tmp_name"]);
        $origem = "upload:arquivo_json";
        $arquivo = $upload["name"] ?? "eventos.json";
    } elseif (isset($_POST["json"]) && trim((string) $_POST["json"]) !== "") {
        $conteudo = (string) $_POST["json"];
        $origem = "post:json";
        $arquivo = null;
    } else {
        $conteudo = file_get_contents("php://input");
        $origem = "raw-body";
        $arquivo = null;
    }

    if ($conteudo === false || trim((string) $conteudo) === "") {
        throw new RuntimeException("JSON não enviado. Envie no body da requisição, no campo POST 'json' ou como upload 'arquivo_json'.");
    }

    $payload = json_decode((string) $conteudo, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("JSON inválido: " . json_last_error_msg());
    }

    if (!is_array($payload)) {
        throw new RuntimeException("O JSON precisa ser um objeto ou uma lista.");
    }

    return [
        "payload" => $payload,
        "origem" => $origem,
        "arquivo" => $arquivo,
    ];
}

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

function extrairEventosDoPayload(array $payload): array
{
    $instituicao = null;
    $eventos = null;

    if (isset($payload["data"])) {
        if (!is_array($payload["data"])) {
            throw new RuntimeException("O campo 'data' precisa ser um objeto JSON.");
        }

        if (array_key_exists("instituicao", $payload["data"])) {
            $instituicao = $payload["data"]["instituicao"];
        }

        if (array_key_exists("eventos", $payload["data"])) {
            $eventos = $payload["data"]["eventos"];
        } elseif (array_key_exists("evento", $payload["data"])) {
            $eventos = $payload["data"]["evento"];
        }
    }

    if ($instituicao === null && array_key_exists("instituicao", $payload)) {
        $instituicao = $payload["instituicao"];
    }

    if ($eventos === null && array_key_exists("eventos", $payload)) {
        $eventos = $payload["eventos"];
    }

    if ($eventos === null && array_key_exists("evento", $payload)) {
        $eventos = $payload["evento"];
    }

    if ($eventos === null) {
        if (arrayEhLista($payload)) {
            $eventos = $payload;
        } elseif (pareceEvento($payload)) {
            $eventos = [$payload];
        } else {
            throw new RuntimeException("Formato inválido. Use {\"status\":\"success\",\"data\":{\"instituicao\":{...},\"eventos\":[...]}}.");
        }
    }

    return [
        "instituicao" => $instituicao,
        "eventos" => normalizarListaObjetos($eventos, "eventos", "pareceEvento"),
    ];
}

function arrayEhLista(array $array): bool
{
    return $array === [] || array_keys($array) === range(0, count($array) - 1);
}

function normalizarListaObjetos($valor, string $campo, callable $detectorObjetoUnico = null): array
{
    if ($valor === null || $valor === "") {
        return [];
    }

    if (!is_array($valor)) {
        throw new RuntimeException("O campo '{$campo}' precisa ser uma lista ou um objeto JSON.");
    }

    if (arrayEhLista($valor)) {
        return $valor;
    }

    if ($detectorObjetoUnico !== null && $detectorObjetoUnico($valor)) {
        return [$valor];
    }

    return array_values($valor);
}

function pareceEvento(array $valor): bool
{
    return array_key_exists("nome", $valor) ||
        array_key_exists("data_inicio", $valor) ||
        array_key_exists("data", $valor) ||
        array_key_exists("atividades", $valor) ||
        array_key_exists("inscritos", $valor);
}

function pareceAtividade(array $valor): bool
{
    return array_key_exists("nome", $valor) ||
        array_key_exists("descricao", $valor) ||
        array_key_exists("horario_inicio", $valor) ||
        array_key_exists("horário_inicio", $valor) ||
        array_key_exists("local", $valor) ||
        array_key_exists("participantes", $valor) ||
        array_key_exists("inscritos", $valor);
}

function parecePessoa(array $valor): bool
{
    return array_key_exists("email", $valor) ||
        array_key_exists("nome", $valor) ||
        array_key_exists("presente", $valor);
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
        return array_map("normalizarPessoa", $valor);
    }

    if (parecePessoa($valor)) {
        return [normalizarPessoa($valor)];
    }

    $saida = [];

    foreach ($valor as $nome => $dados) {
        if (is_array($dados)) {
            $item = $dados;
            $item["nome"] = $item["nome"] ?? (string) $nome;
            $saida[] = normalizarPessoa($item);
            continue;
        }

        if (is_string($dados)) {
            $saida[] = normalizarPessoa([
                "nome" => (string) $nome,
                "email" => $dados,
            ]);
        }
    }

    return $saida;
}

function normalizarPessoa($pessoa): array
{
    if (!is_array($pessoa)) {
        throw new RuntimeException("Pessoa inválida na lista de inscritos/participantes.");
    }

    if (isset($pessoa["email"])) {
        $pessoa["email"] = strtolower(trim((string) $pessoa["email"]));
    }

    if (isset($pessoa["nome"])) {
        $pessoa["nome"] = trim((string) $pessoa["nome"]);
    }

    if (isset($pessoa["cpf"])) {
        $pessoa["cpf"] = trim((string) $pessoa["cpf"]);
    }

    if (isset($pessoa["telefone"])) {
        $pessoa["telefone"] = trim((string) $pessoa["telefone"]);
    }

    return $pessoa;
}

/* -------------------------------------------------------------------------- */
/* Normalização / validação                                                    */
/* -------------------------------------------------------------------------- */

function textoOpcional($valor): ?string
{
    if ($valor === null) {
        return null;
    }

    $texto = trim((string) $valor);

    return $texto === "" ? null : $texto;
}

function normalizarInstituicao($instituicao): ?array
{
    if ($instituicao === null || $instituicao === "") {
        return null;
    }

    if (!is_array($instituicao)) {
        throw new RuntimeException("O campo 'instituicao' precisa ser um objeto JSON.");
    }

    $saida = [
        "nome" => textoOpcional($instituicao["nome"] ?? null),
        "sigla" => textoOpcional($instituicao["sigla"] ?? null),
        "email" => isset($instituicao["email"]) ? strtolower(trim((string) $instituicao["email"])) : null,
    ];

    if ($saida["email"] !== null && $saida["email"] !== "" && !filter_var($saida["email"], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("Email inválido em instituicao.email: {$saida["email"]}.");
    }

    return $saida;
}

function pessoaDaInstituicao(?array $instituicao): ?array
{
    if ($instituicao === null || empty($instituicao["email"])) {
        return null;
    }

    return [
        "nome" => !empty($instituicao["nome"]) ? $instituicao["nome"] : $instituicao["email"],
        "email" => $instituicao["email"],
    ];
}

function normalizarEvento(array $evento, int $indiceEvento, ?array $instituicao): array
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

    $atividades = normalizarListaObjetos($evento["atividades"] ?? [], "atividades do evento {$indiceEvento}", "pareceAtividade");

    $responsavelEmail = $evento["responsavel_evento"]
        ?? $evento["responsavel"]
        ?? $evento["responsável"]
        ?? ($instituicao["email"] ?? null);

    $responsavelNome = $evento["responsavel_nome"]
        ?? $evento["responsavel_evento_nome"]
        ?? ($instituicao["nome"] ?? null);

    $categoria = $evento["categoria"] ?? $evento["modalidade"] ?? "presencial";

    return [
        "nome" => trim((string) $evento["nome"]),
        "descricao" => textoOpcional($evento["descricao"] ?? null),
        "data_inicio" => $dataInicio,
        "data_fim" => $dataFim,
        "responsavel_evento" => textoOpcional($responsavelEmail),
        "responsavel_nome" => textoOpcional($responsavelNome),
        "categoria" => normalizarModalidade((string) $categoria),
        "atividades" => $atividades,
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

    $participantes = normalizarListaPessoas($atividade["inscritos"] ?? $atividade["participantes"] ?? []);
    $modalidade = $atividade["modalidade"] ?? $evento["categoria"] ?? "presencial";

    $dataInicio = null;
    if (!empty($atividade["data_inicio"])) {
        $dataInicio = normalizarData($atividade["data_inicio"], "data_inicio da atividade {$indiceAtividade} do evento {$indiceEvento}");
    } elseif (!empty($atividade["data"])) {
        $dataInicio = normalizarData($atividade["data"], "data da atividade {$indiceAtividade} do evento {$indiceEvento}");
    }

    $dataFim = null;
    if (!empty($atividade["data_fim"])) {
        $dataFim = normalizarData($atividade["data_fim"], "data_fim da atividade {$indiceAtividade} do evento {$indiceEvento}");
    } elseif ($dataInicio !== null) {
        $dataFim = $dataInicio;
    }

    $horarioInicio = normalizarHoraOpcional($atividade["horario_inicio"] ?? $atividade["horário_inicio"] ?? null, "horario_inicio da atividade {$indiceAtividade}");
    $horarioFim = normalizarHoraOpcional($atividade["horario_fim"] ?? $atividade["horário_fim"] ?? null, "horario_fim da atividade {$indiceAtividade}");

    $cargaHoraria = null;

    if (isset($atividade["carga_horaria"]) && $atividade["carga_horaria"] !== "") {
        $cargaHoraria = (int) $atividade["carga_horaria"];
    } else {
        $cargaHoraria = calcularCargaHoraria($horarioInicio, $horarioFim);
    }

    return [
        "nome" => trim((string) $atividade["nome"]),
        "data_inicio" => $dataInicio,
        "data_fim" => $dataFim,
        "horario" => textoOpcional($atividade["horario"] ?? $atividade["horário"] ?? null),
        "horario_inicio" => $horarioInicio,
        "horario_fim" => $horarioFim,
        "local" => textoOpcional($atividade["local"] ?? null),
        "informacoes_atividade" => textoOpcional($atividade["informacoes_atividade"] ?? $atividade["descricao"] ?? null),
        "carga_horaria" => $cargaHoraria,
        "palestrante" => textoOpcional($atividade["palestrante"] ?? null),
        "status_atividade" => normalizarStatusAtividade((string) ($atividade["status_atividade"] ?? "confirmada")),
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

function normalizarHoraOpcional($valor, string $campo): ?string
{
    if ($valor === null || trim((string) $valor) === "") {
        return null;
    }

    $valor = trim((string) $valor);
    $formatos = ["H:i:s", "H:i"];

    foreach ($formatos as $formato) {
        $dt = DateTime::createFromFormat($formato, $valor);

        if ($dt && $dt->format($formato) === $valor) {
            return $dt->format("H:i:s");
        }
    }

    throw new RuntimeException("Horário inválido em {$campo}: {$valor}. Use HH:MM ou HH:MM:SS.");
}

function calcularCargaHoraria(?string $horarioInicio, ?string $horarioFim): ?int
{
    if ($horarioInicio === null || $horarioFim === null) {
        return null;
    }

    $inicio = DateTime::createFromFormat("H:i:s", $horarioInicio);
    $fim = DateTime::createFromFormat("H:i:s", $horarioFim);

    if (!$inicio || !$fim || $fim <= $inicio) {
        return null;
    }

    $segundos = $fim->getTimestamp() - $inicio->getTimestamp();

    return max(1, (int) ceil($segundos / 3600));
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

function participanteEstaPresente(array $participante): bool
{
    if (!array_key_exists("presente", $participante)) {
        return false;
    }

    $valor = $participante["presente"];

    if (is_bool($valor)) {
        return $valor;
    }

    if (is_int($valor)) {
        return $valor === 1;
    }

    if (is_string($valor)) {
        $texto = function_exists("mb_strtolower")
            ? mb_strtolower(trim($valor), "UTF-8")
            : strtolower(trim($valor));

        return in_array($texto, ["1", "true", "sim", "s", "yes", "y", "presente"], true);
    }

    return false;
}

function montarInformacoesAtividade(array $atividade): ?string
{
    $partes = [];

    if (!empty($atividade["informacoes_atividade"])) {
        $partes[] = trim((string) $atividade["informacoes_atividade"]);
    }

    if (!empty($atividade["local"])) {
        $partes[] = "Local: " . $atividade["local"];
    }

    if (!empty($atividade["data_inicio"])) {
        $partes[] = "Data de início: " . $atividade["data_inicio"];
    }

    if (!empty($atividade["data_fim"]) && $atividade["data_fim"] !== $atividade["data_inicio"]) {
        $partes[] = "Data de fim: " . $atividade["data_fim"];
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
        "SELECT id FROM usuario WHERE LOWER(email) = ? LIMIT 1",
        "s",
        [$email]
    );

    if ($row !== null) {
        return (int) $row["id"];
    }

    $senhaTemporaria = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $cpf = textoOpcional($pessoa["cpf"] ?? null);
    $telefone = textoOpcional($pessoa["telefone"] ?? null);

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

function vincularUsuarioAoTipo(mysqli $conn, int $usuarioId, string $tipo): bool
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
        return false;
    }

    $stmt = executar(
        $conn,
        "INSERT INTO e_do (fk_tipo_codigo, fk_usuario_id)
         VALUES (?, ?)",
        "ii",
        [$tipoId, $usuarioId]
    );

    $stmt->close();

    return true;
}

/* -------------------------------------------------------------------------- */
/* Gerencia / Participa                                                        */
/* -------------------------------------------------------------------------- */

function vincularGerencia(mysqli $conn, int $usuarioId, int $eventoId): bool
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
        return false;
    }

    $stmt = executar(
        $conn,
        "INSERT INTO gerencia (fk_usuario_id, fk_evento_codigo)
         VALUES (?, ?)",
        "ii",
        [$usuarioId, $eventoId]
    );

    $stmt->close();

    return true;
}

function vincularParticipanteNaAtividade(mysqli $conn, int $usuarioId, int $atividadeId): bool
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
        return false;
    }

    $stmt = executar(
        $conn,
        "INSERT INTO participa (fk_usuario_id, fk_atividade_id)
         VALUES (?, ?)",
        "ii",
        [$usuarioId, $atividadeId]
    );

    $stmt->close();

    return true;
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

/* -------------------------------------------------------------------------- */
/* Certificado                                                                 */
/* -------------------------------------------------------------------------- */

function inserirCertificadoSeNaoExistir(
    mysqli $conn,
    int $usuarioId,
    int $atividadeId,
    array $participante,
    array $atividade,
    array $evento
): bool {
    $row = buscarUm(
        $conn,
        "SELECT codigo
         FROM certificado
         WHERE fk_usuario_id = ?
           AND fk_atividade_id = ?
         LIMIT 1",
        "ii",
        [$usuarioId, $atividadeId]
    );

    if ($row !== null) {
        return false;
    }

    $nomeParticipante = trim((string) $participante["nome"]);
    $nomeAtividade = trim((string) $atividade["nome"]);
    $nomeEvento = trim((string) $evento["nome"]);

    $textoCertificado = "Certificamos que {$nomeParticipante} participou da atividade \"{$nomeAtividade}\" do evento \"{$nomeEvento}\".";
    $descricao = $atividade["informacoes_atividade"] ?: $nomeAtividade;
    $cargaHoraria = $atividade["carga_horaria"];

    if (certificadoUsaCodigoValidacao($conn)) {
        $codigoValidacao = gerarCodigoValidacao($conn);

        $stmt = executar(
            $conn,
            "INSERT INTO certificado
                (
                    data_emissao,
                    texto_certificado,
                    descricao,
                    carga_horaria,
                    template,
                    qr_code,
                    fk_usuario_id,
                    fk_atividade_id,
                    codigo_validacao
                )
             VALUES (CURDATE(), ?, ?, ?, NULL, NULL, ?, ?, ?)",
            "ssiiis",
            [
                $textoCertificado,
                $descricao,
                $cargaHoraria,
                $usuarioId,
                $atividadeId,
                $codigoValidacao,
            ]
        );
    } else {
        $stmt = executar(
            $conn,
            "INSERT INTO certificado
                (
                    data_emissao,
                    texto_certificado,
                    descricao,
                    carga_horaria,
                    template,
                    qr_code,
                    fk_usuario_id,
                    fk_atividade_id
                )
             VALUES (CURDATE(), ?, ?, ?, NULL, NULL, ?, ?)",
            "ssiii",
            [
                $textoCertificado,
                $descricao,
                $cargaHoraria,
                $usuarioId,
                $atividadeId,
            ]
        );
    }

    $stmt->close();

    return true;
}

function certificadoUsaCodigoValidacao(mysqli $conn): bool
{
    static $cache = null;

    if ($cache === null) {
        $cache = colunaExiste($conn, "certificado", "codigo_validacao");
    }

    return $cache;
}

function gerarCodigoValidacao(mysqli $conn): string
{
    do {
        $codigo = strtoupper(bin2hex(random_bytes(12)));
        $row = buscarUm(
            $conn,
            "SELECT 1 AS existe FROM certificado WHERE codigo_validacao = ? LIMIT 1",
            "s",
            [$codigo]
        );
    } while ($row !== null);

    return $codigo;
}
