<?php
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', 'false'); // troque para 'true' se usar HTTPS
session_start();

// ====== CONFIGURAÇÃO DE CORS ======
$allowed_origins = [
    'http://localhost:8081',
    'http://192.168.1.104:8081',
    'http://192.168.1.104'
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
} else {
    header("Access-Control-Allow-Origin: *"); // fallback para debug
}

// Responde ao preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    http_response_code(204);
    exit();
}

header("Content-Type: application/json; charset=UTF-8");

require("db.php");

// ====== LÓGICA DA SESSÃO ======
if (isset($_SESSION['usuario_nome'])) {
    echo json_encode(["success" => true, "nome" => $_SESSION['usuario_nome']]);
} else {
    echo json_encode(["success" => false, "message" => "Nenhum usuário autenticado"]);
}
?>
