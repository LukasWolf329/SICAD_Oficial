<?php
$servername = 'localhost';
$username = 'root';
$password = 'lukas193';
$dbname = 'sicad';

// Criar conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexão
if ($conn->connect_error) {
    // Se houve erro na conexão
    die("Falha na conexão: " . $conn->connect_error);
}