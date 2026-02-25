<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
  http_response_code(204);
  exit;
}

require("db.php");

$input = json_decode(file_get_contents("php://input"), true);
$userId = isset($input["userId"]) ? (int)$input["userId"] : 0;

if ($userId <= 0) {
  echo json_encode(["success" => false, "eventos" => [], "message" => "userId inválido"]);
  exit;
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

order by e.data_inicio desc;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();

$res = $stmt->get_result();
$eventos = [];

while ($row = $res->fetch_assoc()) {
  $eventos[] = $row;
}

echo json_encode(["success" => true, "eventos" => $eventos], JSON_UNESCAPED_UNICODE);