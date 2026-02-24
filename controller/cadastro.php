<?php
require("db.php");
require("functions.php"); 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['nome']) && isset($data['email']) && isset($data['senha'])) {
    
    $nome  = test_input($data['nome']);
    $email = test_input($data['email']);
    $senha = test_input($data['senha']);

    $senha = password_hash($senha, PASSWORD_DEFAULT);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'E-mail inválido']);
        exit;
    }

    
    $checkEmail = $conn->prepare('SELECT id FROM usuario WHERE email = ?');
    $checkEmail->bind_param('s', $email);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'E-mail já cadastrado']);
        $checkEmail->close();
        exit;
    }
    $checkEmail->close();


    
    $stmt = $conn->prepare('INSERT INTO usuario (nome, email, senha) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $nome, $email, $senha);

    if ($stmt->execute()) {
        echo json_encode(['success' => true , 'message' => 'Usuário cadastrado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar usuário']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
}
?>
