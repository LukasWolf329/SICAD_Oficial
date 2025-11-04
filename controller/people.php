<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");
require("db.php");

// id do evento vindo via GET/POST ou fixo para teste
$evento_id = 1;

$response = [];

$stmt = $conn->prepare("SELECT usuario_nome, usuario_email 
FROM vw_usuarios_evento 
WHERE evento_id = ?;");
$stmt->bind_param("i", $evento_id);

if ($stmt->execute()) {
    $stmt->bind_result($nome, $email);

    while ($stmt->fetch()) {
        $response[] = [
            "nome" => $nome,
            "email" => $email
        ];
    }
}

$stmt->close();
$conn->close();

// devolve em JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
