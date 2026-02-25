<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");
require("db.php");

$input = json_decode(file_get_contents("php://input"), true);

if (is_array($input) && isset($input["evento_id"])) {
    $evento_id = (int)$input["evento_id"];
} elseif (isset($_GET["evento_id"])) {
    $evento_id = (int)$_GET["evento_id"];
} elseif (isset($_POST["evento_id"])) {
    $evento_id = (int)$_POST["evento_id"];
}

$response = [];

$stmt = $conn->prepare("SELECT DISTINCT usuario_nome, usuario_email 
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
