<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

require('db.php');
require('get_certificados.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$evento_id = 1;

$stmt = $conn->prepare('SELECT usuario_nome, usuario_email FROM vw_usuarios_evento WHERE evento_id=?;');
$stmt->bind_param("i", $evento_id);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $totalEnviados = 0;
    $erros = [];

    while ($row = $result->fetch_assoc()) {
        $nome  = $row['usuario_nome'];
        $email = $row['usuario_email'];

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'sicad@atomicmail.io';
            $mail->Password = 'sicad@2025'; // senha de app
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('sicad@atomicmail.io', 'SICAD - Certificados');
            $mail->addAddress($email, $nome);

            $mail->isHTML(true);
            $mail->Subject = 'Certificado do Evento';
            $mail->Body    = "<h1>Olá, $nome!</h1><p>Aqui está o certificado.</p>";
            $mail->AltBody = "Olá $nome, aqui está o certificado.";
            $arquivoParaAnexar = 'imagens/download.png'; 
            $mail->addAttachment($arquivoParaAnexar);

            $mail->send();
            $totalEnviados++;
        } catch (Exception $e) {
            $erros[] = "Erro ao enviar para $email: {$mail->ErrorInfo}";
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "E-mails enviados: $totalEnviados",
        "errors"  => $erros
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Erro ao executar query"
    ]);
}
