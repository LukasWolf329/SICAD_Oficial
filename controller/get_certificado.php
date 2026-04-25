<?php
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

require_once __DIR__ . "/db.php";

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
        echo '{"success":false,"certificados":[],"message":"Falha ao gerar JSON"}';
        exit;
    }

    echo $json;
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    respond(400, [
        "success" => false,
        "certificados" => [],
        "message" => "Body JSON inválido"
    ]);
}

$eventoId = isset($input["evento_id"]) ? (int)$input["evento_id"] : 0;

if ($eventoId <= 0) {
    respond(400, [
        "success" => false,
        "certificados" => [],
        "message" => "evento_id não enviado ou inválido"
    ]);
}

$sql = "
SELECT DISTINCT
    c.codigo AS cod_certificado,
    u.nome   AS participante,
    u.email  AS email,
    IFNULL(c.status_envio, 0) AS status
FROM certificado c
JOIN usuario u ON u.ID = c.fk_Usuario_ID
JOIN atividade a ON a.ID = c.fk_Atividade_ID
WHERE a.fk_Evento_codigo = ?
ORDER BY u.nome ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    respond(500, [
        "success" => false,
        "certificados" => [],
        "message" => "Erro ao preparar consulta",
        "debug" => $conn->error
    ]);
}

$stmt->bind_param("i", $eventoId);

if (!$stmt->execute()) {
    respond(500, [
        "success" => false,
        "certificados" => [],
        "message" => "Erro ao executar consulta",
        "debug" => $stmt->error
    ]);
}

$res = $stmt->get_result();

if (!$res) {
    respond(500, [
        "success" => false,
        "certificados" => [],
        "message" => "Erro ao obter resultado",
        "debug" => $stmt->error
    ]);
}

$out = [];

while ($row = $res->fetch_assoc()) {
    $out[] = [
        "cod_certificado" => (int)($row["cod_certificado"] ?? 0),
        "participante" => (string)($row["participante"] ?? ""),
        "email" => (string)($row["email"] ?? ""),
        "status" => (int)($row["status"] ?? 0),
    ];
}

$stmt->close();
$conn->close();

respond(200, [
    "success" => true,
    "certificados" => $out
]);