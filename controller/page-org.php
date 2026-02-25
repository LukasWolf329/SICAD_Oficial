<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

require('db.php');

$input = json_decode(file_get_contents('php://input'), true);
$evento_id = $input['evento_id'] ?? null;

if (!$evento_id) {
    echo json_encode(["error" => "evento_id não enviado"]);
    exit;
}

$sql = "
    select
        e.nome as evento_nome,

        count(distinct uev.usuario_id)      as total_participantes,
        count(distinct a.id)                as atividades_cadastradas,
        count(distinct c.codigo)            as total_certificados

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
    group by e.codigo, e.nome;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $evento_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(["error" => "Evento não encontrado"]);
}

$stmt->close();
$conn->close();
?>