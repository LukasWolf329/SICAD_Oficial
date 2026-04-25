<?php
$servername = '172.30.0.11';
$username = 'sicad';
$password = 'lwuKK79l34ZnWPeG';
$dbname = 'SICAD';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erro interno ao conectar no banco"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$conn->set_charset("utf8mb4");
