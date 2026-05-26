<?php
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code(204);
    exit;
}

ini_set("display_errors", "0");
ini_set("html_errors", "0");
ini_set("log_errors", "1");
error_reporting(E_ALL);

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

function normalizar_codigo(string $in): ?string
{
    $raw = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($in)));

    if (!is_string($raw) || strlen($raw) !== 24) {
        return null;
    }

    return $raw;
}

function formatar_codigo(string $raw): string
{
    $raw = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($raw)));

    if (!is_string($raw) || strlen($raw) !== 24) {
        return (string)$raw;
    }

    return implode('-', str_split($raw, 4));
}

function obter_codigo_requisicao(): string
{
    $method = $_SERVER["REQUEST_METHOD"] ?? "GET";

    if ($method === "GET") {
        return isset($_GET["codigo"]) ? (string)$_GET["codigo"] : "";
    }

    if ($method === "POST") {
        $rawBody = file_get_contents("php://input");
        $contentType = $_SERVER["CONTENT_TYPE"] ?? "";

        if ($rawBody !== false && trim($rawBody) !== "" && stripos($contentType, "application/json") !== false) {
            $input = json_decode($rawBody, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
                respond(400, [
                    "success" => false,
                    "message" => "JSON inválido"
                ]);
            }

            return isset($input["codigo"]) ? (string)$input["codigo"] : "";
        }

        if (isset($_POST["codigo"])) {
            return (string)$_POST["codigo"];
        }

        return isset($_GET["codigo"]) ? (string)$_GET["codigo"] : "";
    }

    respond(405, [
        "success" => false,
        "message" => "Método não permitido"
    ]);
}

try {
    require_once __DIR__ . "/db.php"; // deve definir $conn como mysqli

    if (!isset($conn) || !($conn instanceof mysqli)) {
        respond(500, [
            "success" => false,
            "message" => "Conexão com o banco não encontrada"
        ]);
    }

    $conn->set_charset("utf8mb4");

    $codigo = obter_codigo_requisicao();
    $codigoRaw = normalizar_codigo($codigo);

    if ($codigoRaw === null) {
        respond(400, [
            "success" => false,
            "message" => "Código inválido. Use o formato XXXX-XXXX-XXXX-XXXX-XXXX-XXXX (24 caracteres)."
        ]);
    }

    $sql = "
        SELECT
            c.codigo_validacao,
            c.data_emissao,
            u.nome AS nome_usuario,
            a.nome AS nome_atividade,
            a.palestrante AS nome_palestrante
        FROM certificado c
        JOIN usuario u ON u.ID = c.fk_Usuario_ID
        JOIN atividade a ON a.ID = c.fk_Atividade_ID
        WHERE REPLACE(REPLACE(UPPER(TRIM(c.codigo_validacao)), '-', ''), ' ', '') = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        respond(500, [
            "success" => false,
            "message" => "Erro ao preparar consulta"
        ]);
    }

    $stmt->bind_param("s", $codigoRaw);

    if (!$stmt->execute()) {
        $stmt->close();

        respond(500, [
            "success" => false,
            "message" => "Erro ao executar consulta"
        ]);
    }

    $codigoValidacao = null;
    $dataEmissao = null;
    $nomeUsuario = null;
    $nomeAtividade = null;
    $nomePalestrante = null;

    $stmt->bind_result(
        $codigoValidacao,
        $dataEmissao,
        $nomeUsuario,
        $nomeAtividade,
        $nomePalestrante
    );

    if (!$stmt->fetch()) {
        $stmt->close();

        respond(200, [
            "success" => true,
            "exists" => false
        ]);
    }

    $stmt->close();

    $codigoDbRaw = normalizar_codigo((string)$codigoValidacao) ?? $codigoRaw;

    respond(200, [
        "success" => true,
        "exists" => true,
        "certificado" => [
            "codigo" => formatar_codigo($codigoDbRaw),
            "data_emissao" => $dataEmissao,
            "nome_usuario" => $nomeUsuario,
            "nome_atividade" => $nomeAtividade,
            "nome_palestrante" => $nomePalestrante
        ]
    ]);
} catch (Throwable $e) {
    error_log("validar_certificado.php: " . $e->getMessage());

    respond(500, [
        "success" => false,
        "message" => "Erro interno ao validar certificado"
    ]);
}