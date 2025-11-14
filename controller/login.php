<?php
// ===== CONFIGURAÇÃO DE CORS =====
$allowed_origins = [
    'http://localhost:8081',
    'http://192.168.1.104:8081',
    'http://192.168.1.104'
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

require("db.php");
require("functions.php");

// ===== LÓGICA DO LOGIN =====
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['email'], $data['senha'])) {

    $email = test_input($data['email']);
    $senha = test_input($data['senha']);

    $stmt = $conn->prepare('SELECT ID, nome, email, senha FROM usuario WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();

        if (password_verify($senha, $usuario['senha'])) {

            // === GERAR TOKEN SEGURO ===
            $token = bin2hex(random_bytes(32)); // 64 chars seguro

            // SALVAR TOKEN NO BANCO (crie tabela se ainda não tiver)
            $stmtToken = $conn->prepare("UPDATE usuario SET token = ? WHERE ID = ?");
            $stmtToken->bind_param("si", $token, $usuario['ID']);
            $stmtToken->execute();

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

echo json_encode(["success" => false, "message" => "Credenciais inválidas"]);
?>
