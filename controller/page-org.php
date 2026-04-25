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
        echo '{"success":false,"message":"Falha ao gerar JSON"}';
        exit;
    }

    echo $json;
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    respond(400, [
        "success" => false,
        "message" => "Body JSON inválido"
    ]);
}

$evento_id = isset($input['evento_id']) ? (int)$input['evento_id'] : 0;

if ($evento_id <= 0) {
    respond(400, [
        "success" => false,
        "message" => "evento_id não enviado ou inválido"
    ]);
}

$sql = "
    select
        e.nome as evento_nome,

        count(distinct uev.usuario_id) as total_participantes,
        count(distinct a.id) as atividades_cadastradas,
        count(distinct c.codigo) as total_certificados

    from evento e

    left join atividade a
        on a.fk_evento_codigo = e.codigo

    left join participa p
        on p.fk_atividade_id = a.id

    left join certificado c
        on c.fk_atividade_id = a.id

    left join (
        select g.fk_evento_codigo as evento_id, g.fk_usuario_id as usuario_id
        from gerencia g
        union
        select a2.fk_evento_codigo as evento_id, p2.fk_usuario_id as usuario_id
        from atividade a2
        join participa p2 on p2.fk_atividade_id = a2.id
    ) uev
        on uev.evento_id = e.codigo

    where e.codigo = ?
    group by e.codigo, e.nome
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    respond(500, [
        "success" => false,
        "message" => "Erro ao preparar consulta",
        "debug" => $conn->error
    ]);
}

$stmt->bind_param("i", $evento_id);

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

$row = $result->fetch_assoc();

$stmt->close();
$conn->close();

if (!$row) {
    respond(404, [
        "success" => false,
        "message" => "Evento não encontrado"
    ]);
}

respond(200, [
    "success" => true,
    "evento_nome" => $row["evento_nome"] ?? "Evento",
    "total_participantes" => (int)($row["total_participantes"] ?? 0),
    "atividades_cadastradas" => (int)($row["atividades_cadastradas"] ?? 0),
    "total_certificados" => (int)($row["total_certificados"] ?? 0)
]);