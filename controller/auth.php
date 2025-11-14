<?php
require "db.php";

// Captura os headers da requisição
$headers = getallheaders();

// Verifica se o header Authorization existe
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["error" => "Não autorizado: token ausente"]);
    exit();
}

// Extrai somente o token
$token = str_replace("Bearer ", "", $headers["Authorization"]);

// Busca o usuário que possui esse token
$stmt = $conn->prepare("SELECT ID, nome, email FROM usuario WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Caso token esteja errado / expirado / inexistente
if (!$user) {
    http_response_code(401);
    echo json_encode(["error" => "Token inválido"]);
    exit();
}


$auth_user = $user;
?>
