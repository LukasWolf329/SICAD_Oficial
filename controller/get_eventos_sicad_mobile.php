<?php
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
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

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    respond(405, [
        "success" => false,
        "eventos" => [],
        "message" => "Método não permitido"
    ]);
}

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    respond(400, [
        "success" => false,
        "eventos" => [],
        "message" => "Body JSON inválido"
    ]);
}

$email = trim((string)($input["email"] ?? ""));

if ($email === "") {
    respond(400, [
        "success" => false,
        "eventos" => [],
        "message" => "E-mail não enviado"
    ]);
}

if (
    strlen($email) > 254 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    respond(400, [
        "success" => false,
        "eventos" => [],
        "message" => "E-mail inválido"
    ]);
}

/*
 * Não depende de vw_usuarios_evento.
 *
 * Um evento é retornado quando o usuário:
 * 1) gerencia diretamente o evento; OU
 * 2) participa de pelo menos uma atividade pertencente ao evento.
 *
 * A consulta parte de usuario.email e usa somente as tabelas físicas:
 * usuario, evento, gerencia, participa e atividade.
 */
$sql = "
    SELECT DISTINCT
        e.codigo,
        e.nome,
        e.data_inicio
    FROM evento AS e

    INNER JOIN (
        /* Usuário como organizador */
        SELECT
            g.fk_Evento_codigo AS evento_codigo
        FROM usuario AS u
        INNER JOIN gerencia AS g
            ON g.fk_Usuario_ID = u.ID
        WHERE LOWER(TRIM(u.email)) = LOWER(TRIM(?))

        UNION

        /* Usuário registrado em atividade */
        SELECT
            a.fk_Evento_codigo AS evento_codigo
        FROM usuario AS u
        INNER JOIN participa AS p
            ON p.fk_Usuario_ID = u.ID
        INNER JOIN atividade AS a
            ON a.ID = p.fk_Atividade_ID
        WHERE LOWER(TRIM(u.email)) = LOWER(TRIM(?))

        UNION

        /* Usuário com certificado emitido */
        SELECT
            a.fk_Evento_codigo AS evento_codigo
        FROM usuario AS u
        INNER JOIN certificado AS c
            ON c.fk_Usuario_ID = u.ID
        INNER JOIN atividade AS a
            ON a.ID = c.fk_Atividade_ID
        WHERE LOWER(TRIM(u.email)) = LOWER(TRIM(?))
    ) AS vinculos
        ON vinculos.evento_codigo = e.codigo

    ORDER BY e.data_inicio DESC, e.codigo DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    respond(500, [
        "success" => false,
        "eventos" => [],
        "message" => "Erro ao preparar consulta"
    ]);
}

$stmt->bind_param(
    "sss",
    $email,
    $email,
    $email
);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();

    respond(500, [
        "success" => false,
        "eventos" => [],
        "message" => "Erro ao executar consulta"
    ]);
}

$result = $stmt->get_result();

if (!$result) {
    $stmt->close();
    $conn->close();

    respond(500, [
        "success" => false,
        "eventos" => [],
        "message" => "Erro ao obter resultado"
    ]);
}

$eventos = [];

while ($row = $result->fetch_assoc()) {
    $eventos[] = [
        "id" => (int)($row["codigo"] ?? 0),
        "nome" => trim((string)($row["nome"] ?? ""))
    ];
}

$stmt->close();
$conn->close();

respond(200, [
    "success" => true,
    "eventos" => $eventos
]);
