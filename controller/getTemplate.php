<?php
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

ini_set("display_errors", "0");
ini_set("html_errors", "0");
error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once __DIR__ . '/db.php';

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

try {
    $atividade_id = isset($_GET['atividade_id']) ? (int) $_GET['atividade_id'] : 0;

    if ($atividade_id <= 0) {
        respond(400, [
            "success" => false,
            "message" => "ID da atividade não fornecido ou inválido."
        ]);
    }

    $sql = "SELECT json, imagem_preview FROM templatecertificado WHERE fk_Atividade_ID = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        respond(500, [
            "success" => false,
            "message" => "Erro ao preparar consulta",
            "debug" => $conn->error
        ]);
    }

    $stmt->bind_param("i", $atividade_id);

    if (!$stmt->execute()) {
        respond(500, [
            "success" => false,
            "message" => "Erro ao executar consulta",
            "debug" => $stmt->error
        ]);
    }

    $result = $stmt->get_result();

    if (!$result) {
        respond(500, [
            "success" => false,
            "message" => "Erro ao obter resultado",
            "debug" => $stmt->error
        ]);
    }

    if ($row = $result->fetch_assoc()) {
        $templateJson = $row['json'] ?? null;
        $imagemPreview = $row['imagem_preview'] ?? null;

        if (!empty($templateJson)) {
            $baseUrl = "https://sicad.linceonline.com.br";

            if (!empty($imagemPreview) && strpos($imagemPreview, "/") === 0) {
                $imagemPreview = $baseUrl . $imagemPreview;
            }

            $stmt->close();
            $conn->close();

            respond(200, [
                "success" => true,
                "template" => [
                    "json" => $templateJson,
                    "imagem_preview" => $imagemPreview
                ]
            ]);
        }

        $stmt->close();
        $conn->close();

        respond(200, [
            "success" => true,
            "template" => null,
            "message" => "Nenhum template salvo ainda."
        ]);
    }

    $stmt->close();
    $conn->close();

    respond(200, [
        "success" => true,
        "template" => null,
        "message" => "Nenhum template salvo ainda."
    ]);

} catch (Throwable $e) {
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn) {
        $conn->close();
    }

    respond(500, [
        "success" => false,
        "message" => $e->getMessage()
    ]);
}