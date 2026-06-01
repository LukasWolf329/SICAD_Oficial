<?php
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

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
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        http_response_code(500);
        echo '{"success":false,"sucesso":false,"message":"Falha ao gerar JSON","mensagem":"Falha ao gerar JSON"}';
        exit;
    }

    echo $json;
    exit;
}

function intOrZero($value): int
{
    if ($value === null || $value === '') {
        return 0;
    }

    return (int)$value;
}

function boolParam($value): bool
{
    if ($value === null) {
        return false;
    }

    $value = strtolower(trim((string)$value));
    return in_array($value, ['1', 'true', 'sim', 'yes'], true);
}

function binaryToDataUrl($binary): ?string
{
    if ($binary === null || $binary === '') {
        return null;
    }

    $mime = 'image/png';

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo) {
            $detected = finfo_buffer($finfo, $binary);
            finfo_close($finfo);

            if (is_string($detected) && strpos($detected, 'image/') === 0) {
                $mime = $detected;
            }
        }
    }

    return 'data:' . $mime . ';base64,' . base64_encode($binary);
}

function normalizeTemplateRow(array $row, bool $withRender): array
{
    $jsonRaw = isset($row['json']) ? (string)$row['json'] : '{}';
    $jsonObj = json_decode($jsonRaw, true);

    return [
        "id" => isset($row['id']) ? (int)$row['id'] : null,
        "nome" => isset($row['nome']) ? $row['nome'] : '',
        "json" => $jsonRaw,
        "json_obj" => is_array($jsonObj) ? $jsonObj : null,
        "imagem_preview" => binaryToDataUrl($row['imagem_preview'] ?? null),
        "imagem_render" => $withRender ? binaryToDataUrl($row['imagem_render'] ?? null) : null,
        "data_criacao" => $row['data_criacao'] ?? null,
        "fk_Atividade_ID" => isset($row['fk_Atividade_ID']) ? (int)$row['fk_Atividade_ID'] : null,
        "atividade_id" => isset($row['fk_Atividade_ID']) ? (int)$row['fk_Atividade_ID'] : null
    ];
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "GET") {
        respond(405, [
            "success" => false,
            "sucesso" => false,
            "message" => "Use GET para buscar o template",
            "mensagem" => "Use GET para buscar o template"
        ]);
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        respond(500, [
            "success" => false,
            "sucesso" => false,
            "message" => "Conexão mysqli não encontrada. No db.php, deixe a conexão na variável \$conn",
            "mensagem" => "Conexão mysqli não encontrada. No db.php, deixe a conexão na variável \$conn"
        ]);
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->set_charset('utf8mb4');

    $id = intOrZero($_GET['id'] ?? ($_GET['template_id'] ?? ($_GET['id_template'] ?? null)));
    $atividadeId = intOrZero($_GET['atividade_id'] ?? ($_GET['fk_Atividade_ID'] ?? ($_GET['id_atividade'] ?? null)));
    $withRender = boolParam($_GET['with_render'] ?? ($_GET['render'] ?? null));

    if ($id <= 0 && $atividadeId <= 0) {
        respond(400, [
            "success" => false,
            "sucesso" => false,
            "message" => "Informe id ou atividade_id",
            "mensagem" => "Informe id ou atividade_id"
        ]);
    }

    $fields = 'id, nome, `json`, imagem_preview, data_criacao, fk_Atividade_ID';

    if ($withRender) {
        $fields .= ', imagem_render';
    }

    if ($id > 0) {
        $stmt = $conn->prepare('SELECT ' . $fields . ' FROM templatecertificado WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
    } else {
        $stmt = $conn->prepare('SELECT ' . $fields . ' FROM templatecertificado WHERE fk_Atividade_ID = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('i', $atividadeId);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        $stmt->close();
        respond(404, [
            "success" => false,
            "sucesso" => false,
            "message" => "Template não encontrado",
            "mensagem" => "Template não encontrado"
        ]);
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    $template = normalizeTemplateRow($row, $withRender);

    respond(200, [
        "success" => true,
        "sucesso" => true,
        "template" => $template,
        "data" => $template
    ]);
} catch (Throwable $e) {
    respond(500, [
        "success" => false,
        "sucesso" => false,
        "message" => "Erro ao buscar template: " . $e->getMessage(),
        "mensagem" => "Erro ao buscar template: " . $e->getMessage()
    ]);
}
