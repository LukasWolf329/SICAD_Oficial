<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

require('db.php');

// RECEBE O JSON DO FETCH
$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['userId'] ?? null;

// VALIDA
if (!$userId || intval($userId) <= 0) {
    echo json_encode(["error" => "userId inválido"]);
    exit;
}

$sql = "
SELECT
    e.codigo AS evento_id,
    e.nome AS evento_nome,
    e.data_inicio,
    e.data_fim,

    CASE
        WHEN g.fk_Usuario_ID IS NOT NULL THEN 'organizador'
        ELSE 'participante'
    END AS status_usuario,

    CASE
        WHEN g.fk_Usuario_ID IS NOT NULL THEN (
            SELECT COUNT(DISTINCT p2.fk_Usuario_ID)
            FROM Participa p2
            JOIN Atividade a2 ON a2.ID = p2.fk_Atividade_ID
            WHERE a2.fk_Evento_codigo = e.codigo
        )
        ELSE NULL
    END AS total_participantes

FROM Evento e

LEFT JOIN Gerencia g
       ON g.fk_Evento_codigo = e.codigo
      AND g.fk_Usuario_ID = ?

LEFT JOIN Atividade a
       ON a.fk_Evento_codigo = e.codigo

LEFT JOIN Participa p
       ON p.fk_Atividade_ID = a.ID
      AND p.fk_Usuario_ID = ?

WHERE g.fk_Usuario_ID IS NOT NULL
   OR p.fk_Usuario_ID IS NOT NULL

GROUP BY e.codigo, status_usuario, total_participantes
ORDER BY e.data_inicio DESC;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$eventos = [];

while ($row = $result->fetch_assoc()) {
    $eventos[] = [
        "evento_id" => $row["evento_id"],
        "evento_nome" => $row["evento_nome"],
        "data_inicio" => date("d/m/Y", strtotime($row["data_inicio"])),
        "data_fim" => date("d/m/Y", strtotime($row["data_fim"])),
        "status_usuario" => $row["status_usuario"],
        "total_participantes" => $row["total_participantes"],
    ];
}

echo json_encode(["eventos" => $eventos], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$conn->close();
