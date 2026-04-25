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

function respond(int $status, array $payload): void {
    if (ob_get_length()) {
        ob_clean(); // limpa qualquer "Conectou!" ou saída acidental
    }

    http_response_code($status);

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        http_response_code(500);
        echo '{"success":false,"eventos":[],"message":"Falha ao gerar JSON"}';
        exit;
    }

    echo $json;
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

$userId = isset($input["userId"]) ? (int)$input["userId"] : 0;

if ($userId <= 0) {
    respond(400, [
        "success" => false,
        "eventos" => [],
        "message" => "userId inválido"
    ]);
}

$sql = "
select
    e.codigo as evento_id,
    e.nome as evento_nome,
    e.data_inicio,
    e.data_fim,

    case
        when g.fk_usuario_id is not null then 'organizador'
        else 'participante'
    end as status_usuario,

    case
        when g.fk_usuario_id is not null then (
            select count(distinct p2.fk_usuario_id)
            from participa p2
            join atividade a2 on a2.id = p2.fk_atividade_id
            where a2.fk_evento_codigo = e.codigo
        )
        else null
    end as total_participantes

from evento e

left join gerencia g
       on g.fk_evento_codigo = e.codigo
      and g.fk_usuario_id = ?

left join atividade a
       on a.fk_evento_codigo = e.codigo

left join participa p
       on p.fk_atividade_id = a.id
      and p.fk_usuario_id = ?

where g.fk_usuario_id is not null
   or p.fk_usuario_id is not null

group by
    e.codigo, e.nome, e.data_inicio, e.data_fim,
    status_usuario, total_participantes

order by e.data_inicio desc
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    respond(500, [
        "success" => false,
        "eventos" => [],
        "message" => "Erro ao preparar consulta",
        "debug" => $conn->error
    ]);
}

if (!$stmt->bind_param("ii", $userId, $userId)) {
    respond(500, [
        "success" => false,
        "eventos" => [],
        "message" => "Erro ao vincular parâmetros",
        "debug" => $stmt->error
    ]);
}

if (!$stmt->execute()) {
    respond(500, [
        "success" => false,
        "eventos" => [],
        "message" => "Erro ao executar consulta",
        "debug" => $stmt->error
    ]);
}

$res = $stmt->get_result();

if (!$res) {
    respond(500, [
        "success" => false,
        "eventos" => [],
        "message" => "Erro ao obter resultados",
        "debug" => $stmt->error
    ]);
}

$eventos = [];

while ($row = $res->fetch_assoc()) {
    $eventos[] = $row;
}

$stmt->close();

respond(200, [
    "success" => true,
    "eventos" => $eventos
]);