<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

require('db.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

$eventoId = 1;

$sql = "SELECT 
        c.codigo AS cod_certificado,
        c.template AS pdf_blob,
        u.nome AS nome_usuario,
        u.email AS email_usuario,
        e.nome AS nome_evento
    FROM Certificado c
    INNER JOIN Usuario u ON u.ID = c.fk_Usuario_ID
    INNER JOIN Atividade a ON a.ID = c.fk_Atividade_ID
    INNER JOIN Evento e ON e.codigo = a.fk_Evento_codigo
    WHERE e.codigo = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $eventoId);
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {

    /* 🔹 CRIA O ARQUIVO TEMPORÁRIO IGUAL AO OUTRO ENDPOINT */
    $tempFilePath = sys_get_temp_dir() . "/certificado_" . $row['cod_certificado'] . ".pdf";

    $pdfData = $row['pdf_blob'];

    // se vier base64 do banco, decodifica
    if (base64_encode(base64_decode($pdfData, true)) === $pdfData) {
        $pdfData = base64_decode($pdfData);
    }

    file_put_contents($tempFilePath, $pdfData);

    try {
        /* 🔹 CONFIGURANDO E-MAIL */
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lukasjuliuswolf@gmail.com';
        $mail->Password = 'khin fxqc ohye rewu'; // senha de app
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = "UTF-8";

        $mail->setFrom('lukasjuliuswolf@gmail.com', 'SICAD - Certificados');
        $mail->addAddress($row['email_usuario'], $row['nome_usuario']);
        $mail->Subject = 'Seu certificado está disponível!';
        $mail->Body = "Olá {$row['nome_usuario']},\n\nSegue em anexo o certificado referente ao evento {$row['nome_evento']}.\n\nAtenciosamente,\nEquipe SICAD";

        $mail->addAttachment($tempFilePath, "certificado.pdf");

        $mail->send();

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $mail->ErrorInfo,
            'email' => $row['email_usuario']
        ]);
    } finally {
        /* 🔹 Remove arquivo temporário */
        if (file_exists($tempFilePath)) {
            unlink($tempFilePath);
        }
    }
}

echo json_encode(['success' => true, 'message' => 'Certificados enviados com sucesso!']);
?>
