<?php
$servername = '172.30.0.11';
$username = 'sicad';
$password = 'lwuKK79l34ZnWPeG';
$dbname = 'SICAD';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    
    die("Falha na conexão: " . $conn->connect_error);
}

echo "Conectou!";