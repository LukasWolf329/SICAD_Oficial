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

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    respond(400, [
        "success" => false,
        "eventos" => [],
        "message" => "Body JSON inválido"
    ]);
}

$email = isset($input["email"]) ? $input["email"] : "";

if($email == "") {
    respond(400, [
        "success" => false,
        "eventos" => [],
        "message" => "email não enviado"
    ]);
}

$sql = "
    SELECT DISTINCT
        e.codigo,
        e.nome
    FROM evento AS e

    INNER JOIN vw_usuarios_evento AS v
        ON v.evento_id = e.codigo

    WHERE v.usuario_email = ?

    ORDER BY e.data_inicio DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    throw new RuntimeException($conn->error);
}

$stmt->bind_param("s", $email);

if (!$stmt->execute()) {
    respond(500, [
        "success" => false,
        "pessoas" => [],
        "message" => "Erro ao executar consulta",
        "debug" => $stmt->error
    ]);
}

$result = $stmt->get_result();

if (!$result) {
    respond(500, [
        "success" => false,
        "pessoas" => [],
        "message" => "Erro ao obter resultado",
        "debug" => $stmt->error
    ]);
}

$eventos = [];

while ($row = $result->fetch_assoc()) {
    $eventos[] = [
        "id" => (int)($row["codigo"] ?? 0),
        "nome" => (string)($row["nome"] ?? "")
    ];
}

$stmt->close();
$conn->close();

respond(200, [
    "success" => true,
    "eventos" => $eventos
]);
