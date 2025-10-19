<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");
require('db.php');
$response = [];
$evento_id = 1;

$stmt = $conn->prepare("SELECT DISTINCT a.nome AS atividade_nome
FROM Certificado c
JOIN Atividade a ON c.fk_Atividade_ID = a.ID
JOIN Evento e ON a.fk_Evento_codigo = e.codigo
WHERE e.codigo = ?
ORDER BY a.nome;");
$stmt->bind_param("i", $evento_id);


if ($stmt->execute()) {
    $stmt->bind_result($atividade_nome);

    while ($stmt->fetch()) {
        $response[] = [
            "titulo" => $atividade_nome
        ];
    }
}

$stmt->close();
$conn->close();

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>