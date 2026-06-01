<?php
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

function firstValue(array $data, array $keys, $default = null)
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }
    }

    return $default;
}

function hasAnyKey(array $data, array $keys): bool
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $data)) {
            return true;
        }
    }

    return false;
}

function intOrZero($value): int
{
    if ($value === null || $value === '') {
        return 0;
    }

    return (int)$value;
}

function readJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $rawTrimmed = trim((string)$raw);

    if ($rawTrimmed !== '') {
        $decoded = json_decode($rawTrimmed, true);

        if (!is_array($decoded)) {
            respond(400, [
                "success" => false,
                "sucesso" => false,
                "message" => "JSON recebido é inválido: " . json_last_error_msg(),
                "mensagem" => "JSON recebido é inválido: " . json_last_error_msg()
            ]);
        }

        return $decoded;
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    return [];
}

function normalizeTemplateJson($value): string
{
    if (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    if (!is_string($value) || trim($value) === '') {
        respond(400, [
            "success" => false,
            "sucesso" => false,
            "message" => "O campo json do template é obrigatório",
            "mensagem" => "O campo json do template é obrigatório"
        ]);
    }

    json_decode($value, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        respond(400, [
            "success" => false,
            "sucesso" => false,
            "message" => "O campo json do template está inválido: " . json_last_error_msg(),
            "mensagem" => "O campo json do template está inválido: " . json_last_error_msg()
        ]);
    }

    return $value;
}

function dataUrlToBinary($dataUrl, string $fieldName): ?string
{
    if ($dataUrl === null || $dataUrl === '') {
        return null;
    }

    if (!is_string($dataUrl)) {
        respond(400, [
            "success" => false,
            "sucesso" => false,
            "message" => "O campo {$fieldName} precisa ser string base64",
            "mensagem" => "O campo {$fieldName} precisa ser string base64"
        ]);
    }

    $base64 = $dataUrl;

    if (preg_match('/^data:([^;]+);base64,(.*)$/s', $dataUrl, $matches)) {
        $mime = strtolower(trim($matches[1]));
        $base64 = $matches[2];

        $allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];

        if (!in_array($mime, $allowed, true)) {
            respond(400, [
                "success" => false,
                "sucesso" => false,
                "message" => "Formato de imagem não permitido em {$fieldName}. Use PNG, JPEG ou WEBP",
                "mensagem" => "Formato de imagem não permitido em {$fieldName}. Use PNG, JPEG ou WEBP"
            ]);
        }
    }

    $base64 = preg_replace('/\s+/', '', $base64);
    $binary = base64_decode($base64, true);

    if ($binary === false) {
        respond(400, [
            "success" => false,
            "sucesso" => false,
            "message" => "Imagem inválida em {$fieldName}",
            "mensagem" => "Imagem inválida em {$fieldName}"
        ]);
    }

    $limitBytes = 10 * 1024 * 1024;

    if (strlen($binary) > $limitBytes) {
        respond(413, [
            "success" => false,
            "sucesso" => false,
            "message" => "{$fieldName} passou de 10 MB. Gere uma imagem menor ou salve somente o preview",
            "mensagem" => "{$fieldName} passou de 10 MB. Gere uma imagem menor ou salve somente o preview"
        ]);
    }

    return $binary;
}

function bindParams(mysqli_stmt $stmt, string $types, array &$params): void
{
    $refs = [];
    $refs[] = $types;

    foreach ($params as $key => &$value) {
        $refs[] = &$value;
    }

    call_user_func_array([$stmt, 'bind_param'], $refs);
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        respond(405, [
            "success" => false,
            "sucesso" => false,
            "message" => "Use POST para salvar o template",
            "mensagem" => "Use POST para salvar o template"
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

    $data = readJsonInput();

    $id = intOrZero(firstValue($data, ['id', 'template_id', 'id_template']));
    $atividadeId = intOrZero(firstValue($data, ['fk_Atividade_ID', 'fk_atividade_id', 'atividade_id', 'id_atividade']));

    $nome = trim((string)firstValue($data, ['nome', 'nome_template', 'titulo'], 'Template de certificado'));

    if ($nome === '') {
        $nome = 'Template de certificado';
    }

    if (mb_strlen($nome) > 255) {
        $nome = mb_substr($nome, 0, 255);
    }

    $jsonTemplate = normalizeTemplateJson(firstValue($data, ['json', 'template_json', 'conteudo_json']));

    $hasPreview = hasAnyKey($data, ['imagem_preview', 'preview', 'imagemPreview', 'canvas_image', 'canvasImage']);
    $hasRender = hasAnyKey($data, ['imagem_render', 'render', 'imagemRender']);

    $imagemPreview = $hasPreview
        ? dataUrlToBinary(firstValue($data, ['imagem_preview', 'preview', 'imagemPreview', 'canvas_image', 'canvasImage']), 'imagem_preview')
        : null;

    $imagemRender = $hasRender
        ? dataUrlToBinary(firstValue($data, ['imagem_render', 'render', 'imagemRender']), 'imagem_render')
        : null;

    if ($id > 0) {
        $sets = ['nome = ?', '`json` = ?'];
        $types = 'ss';
        $params = [$nome, $jsonTemplate];

        if ($atividadeId > 0) {
            $sets[] = 'fk_Atividade_ID = ?';
            $types .= 'i';
            $params[] = $atividadeId;
        }

        if ($hasPreview) {
            $sets[] = 'imagem_preview = ?';
            $types .= 's';
            $params[] = $imagemPreview;
        }

        if ($hasRender) {
            $sets[] = 'imagem_render = ?';
            $types .= 's';
            $params[] = $imagemRender;
        }

        $sql = 'UPDATE templatecertificado SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $types .= 'i';
        $params[] = $id;

        $stmt = $conn->prepare($sql);
        bindParams($stmt, $types, $params);
        $stmt->execute();
        $stmt->close();

        respond(200, [
            "success" => true,
            "sucesso" => true,
            "id" => $id,
            "template_id" => $id,
            "message" => "Template salvo com sucesso",
            "mensagem" => "Template salvo com sucesso"
        ]);
    }

    if ($atividadeId <= 0) {
        respond(400, [
            "success" => false,
            "sucesso" => false,
            "message" => "atividade_id ou fk_Atividade_ID é obrigatório para criar um template novo",
            "mensagem" => "atividade_id ou fk_Atividade_ID é obrigatório para criar um template novo"
        ]);
    }

    $sql = '
        INSERT INTO templatecertificado
            (nome, `json`, imagem_preview, imagem_render, fk_Atividade_ID)
        VALUES
            (?, ?, ?, ?, ?)
    ';

    $stmt = $conn->prepare($sql);
    $params = [$nome, $jsonTemplate, $hasPreview ? $imagemPreview : null, $hasRender ? $imagemRender : null, $atividadeId];
    bindParams($stmt, 'ssssi', $params);
    $stmt->execute();
    $newId = (int)$conn->insert_id;
    $stmt->close();

    respond(200, [
        "success" => true,
        "sucesso" => true,
        "id" => $newId,
        "template_id" => $newId,
        "message" => "Template salvo com sucesso",
        "mensagem" => "Template salvo com sucesso"
    ]);
} catch (Throwable $e) {
    respond(500, [
        "success" => false,
        "sucesso" => false,
        "message" => "Erro ao salvar template: " . $e->getMessage(),
        "mensagem" => "Erro ao salvar template: " . $e->getMessage()
    ]);
}
