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
SELECT 
    e.nome AS evento_nome,

    COALESCE((
        SELECT COUNT(DISTINCT v.usuario_id)
        FROM vw_num_cadastrados_evento v
        WHERE v.evento_id = e.codigo
    ), 0) AS total_participantes,

    COALESCE((
        SELECT COUNT(*)
        FROM Atividade a
        WHERE a.fk_Evento_codigo = e.codigo
    ), 0) AS atividades_cadastradas,

    COALESCE((
        SELECT total_certificados
        FROM total_certificados_evento tc
        WHERE tc.evento_id = e.codigo
    ), 0) AS total_certificados

FROM Evento e
WHERE e.codigo = ?;
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
