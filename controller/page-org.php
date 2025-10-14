<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");
require('db.php');

$evento_id = 1;
$response = [];

// ---------------- total de participantes ----------------
$stmt = $conn->prepare("SELECT COUNT(DISTINCT usuario_id) AS total_cadastrados
FROM vw_num_cadastrados_evento
WHERE evento_id = ?;
");

$stmt->bind_param("i", $evento_id);

if ($stmt->execute()) {
    $stmt->bind_result($total_participantes);
    if ($stmt->fetch()) {
        $response['total_participantes'] = (int)$total_participantes;
    } else {
        $response['total_participantes'] = 0;
    }
}
$stmt->close();

// ---------------- total de atividades ----------------
$stmt = $conn->prepare('
    SELECT COUNT(*) AS total_atividades
    FROM Atividade
    WHERE fk_Evento_codigo = ?
');
$stmt->bind_param("i", $evento_id);

if ($stmt->execute()) {
    $stmt->bind_result($total_atividades);
    if ($stmt->fetch()) {
        $response['atividades_cadastradas'] = (int)$total_atividades;
    } else {
        $response['atividades_cadastradas'] = 0;
    }
}
$stmt->close();

// ---------------- total de certificados ----------------
$stmt = $conn->prepare('
    SELECT total_certificados 
    FROM total_certificados_evento 
    WHERE evento_id = ?
');
$stmt->bind_param("i", $evento_id);

if ($stmt->execute()) {
    $stmt->bind_result($total_certificados);
    if ($stmt->fetch()) {
        $response['total_certificados'] = (int)$total_certificados;
    } else {
        $response['total_certificados'] = 0;
    }
}
$stmt->close();

// ---------------- fecha conexão e responde ----------------
$conn->close();

echo json_encode($response);
?>
