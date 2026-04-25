<?php
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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
        echo '{"success":false,"certificados":[],"message":"Falha ao gerar JSON"}';
        exit;
    }

    echo $json;
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    respond(400, [
        "success" => false,
        "certificados" => [],
        "message" => "Body JSON inválido"
    ]);
}

$evento_id = isset($input['evento_id']) ? (int)$input['evento_id'] : 0;

if ($evento_id <= 0) {
    respond(400, [
        "success" => false,
        "certificados" => [],
        "message" => "evento_id não enviado ou inválido"
    ]);
}

$sql = "
    SELECT DISTINCT
        a.ID AS atividade_id,
        a.nome AS titulo
    FROM certificado c
    JOIN atividade a ON a.ID = c.fk_Atividade_ID
    JOIN evento e ON e.codigo = a.fk_Evento_codigo
    WHERE e.codigo = ?
    ORDER BY a.nome
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

$stmt->bind_param("i", $evento_id);

if (!$stmt->execute()) {
    respond(500, [
        "success" => false,
        "certificados" => [],
        "message" => "Erro ao executar consulta",
        "debug" => $stmt->error
    ]);
}

$result = $stmt->get_result();

if (!$result) {
    respond(500, [
        "success" => false,
        "certificados" => [],
        "message" => "Erro ao obter resultado",
        "debug" => $stmt->error
    ]);
}

$certificados = [];

while ($row = $result->fetch_assoc()) {
    $certificados[] = [
        "titulo" => (string)($row["titulo"] ?? ""),
        "atividade_id" => (int)($row["atividade_id"] ?? 0),
    ];
}

$stmt->close();
$conn->close();

respond(200, [
    "success" => true,
    "certificados" => $certificados
]);