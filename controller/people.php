<?php
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

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
        echo '{"success":false,"pessoas":[],"message":"Falha ao gerar JSON"}';
        exit;
    }

    echo $json;
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    respond(400, [
        "success" => false,
        "pessoas" => [],
        "message" => "Body JSON inválido"
    ]);
}

$evento_id = isset($input["evento_id"]) ? (int)$input["evento_id"] : 0;

if ($evento_id <= 0) {
    respond(400, [
        "success" => false,
        "pessoas" => [],
        "message" => "evento_id não enviado ou inválido"
    ]);
}

$sql = "
    SELECT DISTINCT
        COALESCE(NULLIF(TRIM(u.nome), ''), '(sem nome)') AS nome,
        COALESCE(NULLIF(TRIM(u.email), ''), '(sem e-mail)') AS email
    FROM usuario u
    JOIN (
        SELECT g.fk_Usuario_ID AS usuario_id
        FROM gerencia g
        WHERE g.fk_Evento_codigo = ?

        UNION

        SELECT p.fk_Usuario_ID AS usuario_id
        FROM participa p
        JOIN atividade a ON a.ID = p.fk_Atividade_ID
        WHERE a.fk_Evento_codigo = ?
    ) x ON x.usuario_id = u.ID
    ORDER BY nome ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    respond(500, [
        "success" => false,
        "pessoas" => [],
        "message" => "Erro ao preparar consulta",
        "debug" => $conn->error
    ]);
}

$stmt->bind_param("ii", $evento_id, $evento_id);

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

$pessoas = [];

while ($row = $result->fetch_assoc()) {
    $pessoas[] = [
        "nome" => (string)($row["nome"] ?? ""),
        "email" => (string)($row["email"] ?? ""),
    ];
}

$stmt->close();
$conn->close();

respond(200, [
    "success" => true,
    "pessoas" => $pessoas
]);