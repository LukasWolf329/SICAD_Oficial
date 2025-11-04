<?php
// ===== CONFIGURAÇÃO DE CORS =====
$allowed_origins = [
    'http://localhost:8081',
    'http://192.168.1.106:8081',
    'http://192.168.1.106'
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// ===== TRATA O PRÉ-FLIGHT (OPTIONS) =====
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// ===== CONFIGURAÇÕES DE SESSÃO =====
ini_set('session.cookie_samesite', 'None');  // Permite cookies cross-origin
ini_set('session.cookie_secure', 'false');   // true se estiver em HTTPS
ini_set('session.cookie_httponly', 'true');  // evita acesso via JS
session_start();

require("db.php");
require("functions.php");

// ===== LÓGICA DO LOGIN =====
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

            // Cria sessão
            $_SESSION['usuario_id'] = $usuario['ID'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];

            // Debug opcional
            // error_log("Sessão criada com ID: " . session_id());

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
