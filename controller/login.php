<?php
require("db.php");
require("test_input.php");

session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['email']) && isset($data['senha'])) {
    $email = test_input($data['email']);
    $senha = test_input($data['senha']);

    $stmt = $conn->prepare('SELECT ID, nome, email, senha FROM usuario WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();

        if (password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['ID'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];

            echo json_encode([
                "success" => true,
                "usuario" => [
                    "id" => $usuario['ID'],
                    "nome" => $usuario['nome'],
                    "email" => $usuario['email']
                ]
            ]);
            exit();
        }
    }
}

echo json_encode(["success" => false, "message" => "Credenciais inválidas"]);

?>