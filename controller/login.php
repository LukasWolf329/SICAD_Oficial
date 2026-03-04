<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST,GET , OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require("db.php");
require("functions.php");

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['email'], $data['senha'])) {

    $email = test_input($data['email']);
    $senha = test_input($data['senha']);

    $stmt = $conn->prepare('SELECT ID, nome, email, senha, email_verificado FROM usuario WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        if (password_verify($senha, $usuario['senha'])) {

            if ((int) $usuario['email_verificado'] === 0) {
                echo json_encode([
                    "success" => false,
                    "code" => "EMAIL_NOT_VERIFIED",
                    "message" => "Seu e-mail ainda não foi verificado. Digite o token enviado para seu e-mail."
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $token = bin2hex(random_bytes(32));


            $stmtToken = $conn->prepare("UPDATE usuario SET token = ? WHERE ID = ?");
            $stmtToken->bind_param("si", $token, $usuario['ID']);
            $stmtToken->execute();
            $_POST['id_usuario'] = $usuario['ID'];
            echo json_encode([
                "success" => true,
                "token" => $token,
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

echo json_encode([
    "success" => false,
    "message" => "Credenciais inválidas",
    "debug" => [
        "email" => $email,
        "senhaRecebida" => $senha,
        "usuarioEncontrado" => isset($usuario) ? $usuario : null
    ]
]);

?>