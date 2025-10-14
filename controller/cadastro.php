<?php
require("db.php");
require("test_input");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['nome']) && isset($data['email']) && isset($data['senha'])) {
    $nome  = test_input($data['nome']);
    $email = test_input($data['email']);
    $senha = password_hash($data['senha'], PASSWORD_DEFAULT);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'E-mail inválido']);
        exit;
    }

    $stmt = $conn->prepare('INSERT INTO usuario (nome, email, senha) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $nome, $email, $senha);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Usuário cadastrado com sucesso']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao cadastrar usuário']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Dados incompletos']);
}


?>
