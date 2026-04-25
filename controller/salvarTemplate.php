<?php
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

ini_set("display_errors", "0");
ini_set("html_errors", "0");
error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});


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
    require_once __DIR__ . '/db.php';

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        respond(400, [
            "success" => false,
            "message" => "Body JSON inválido"
        ]);
    }

    if (!isset($data['json'], $data['atividade_id'])) {
        respond(400, [
            "success" => false,
            "message" => "Dados incompletos. 'json' e 'atividade_id' são obrigatórios."
        ]);
    }

    $json = (string) $data['json'];
    $atividade_id = (int) $data['atividade_id'];

    if ($atividade_id <= 0) {
        respond(400, [
            "success" => false,
            "message" => "atividade_id inválido"
        ]);
    }

    $imagem_src = $data['imagem_src'] ?? null;
    $imagem_preview = null;

    if (is_string($imagem_src) && trim($imagem_src) !== "") {
        $decoded = urldecode($imagem_src);

        if (strpos($decoded, '/controller/assets/certificados/') !== false) {
            $imagem_preview = substr(
                $decoded,
                strpos($decoded, '/controller/assets/certificados/')
            );
        } elseif (strpos($decoded, '/assets/certificados/') !== false) {
            $imagem_preview = '/controller' . substr(
                $decoded,
                strpos($decoded, '/assets/certificados/')
            );
        } else {
            respond(400, [
                "success" => false,
                "message" => "Caminho da imagem inválido"
            ]);
        }
    }

    $sqlCheck = "SELECT id FROM templatecertificado WHERE fk_Atividade_ID = ?";
    $stmtCheck = $conn->prepare($sqlCheck);

    if (!$stmtCheck) {
        respond(500, [
            "success" => false,
            "message" => "Erro ao preparar verificação",
            "debug" => $conn->error
        ]);
    }

    $stmtCheck->bind_param("i", $atividade_id);

    if (!$stmtCheck->execute()) {
        respond(500, [
            "success" => false,
            "message" => "Erro ao executar verificação",
            "debug" => $stmtCheck->error
        ]);
    }

    $resultCheck = $stmtCheck->get_result();

    if (!$resultCheck) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao obter resultado da verificação",
        "debug" => $stmtCheck->error
    ]);
}

    if ($resultCheck->num_rows > 0) {
        if ($imagem_preview !== null) {
            $sql = "UPDATE templatecertificado
                    SET json = ?, imagem_preview = ?
                    WHERE fk_Atividade_ID = ?";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                respond(500, [
                    "success" => false,
                    "message" => "Erro ao preparar UPDATE",
                    "debug" => $conn->error
                ]);
            }

            $stmt->bind_param("ssi", $json, $imagem_preview, $atividade_id);
        } else {
            $sql = "UPDATE templatecertificado
                    SET json = ?
                    WHERE fk_Atividade_ID = ?";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                respond(500, [
                    "success" => false,
                    "message" => "Erro ao preparar UPDATE",
                    "debug" => $conn->error
                ]);
            }

            $stmt->bind_param("si", $json, $atividade_id);
        }
    } else {
        if ($imagem_preview === null) {
            respond(400, [
                "success" => false,
                "message" => "Selecione um modelo de certificado antes de salvar."
            ]);
        }

        $sql = "INSERT INTO templatecertificado
                (nome, json, imagem_preview, fk_Atividade_ID, data_criacao)
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            respond(500, [
                "success" => false,
                "message" => "Erro ao preparar INSERT",
                "debug" => $conn->error
            ]);
        }

        $nome = 'Template atividade ' . $atividade_id;
        $stmt->bind_param("sssi", $nome, $json, $imagem_preview, $atividade_id);
    }

    if (!$stmt->execute()) {
        respond(500, [
            "success" => false,
            "message" => "Erro ao salvar no banco",
            "debug" => $stmt->error
        ]);
    }

    if (isset($stmtCheck)) {
        $stmtCheck->close();
    }
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }

    respond(200, [
        "success" => true,
        "atividade_id" => $atividade_id,
        "imagem_preview" => $imagem_preview,
        "message" => "Template salvo com sucesso."
    ]);

} catch (Throwable $e) {
    if (isset($stmtCheck) && $stmtCheck) {
        $stmtCheck->close();
    }
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