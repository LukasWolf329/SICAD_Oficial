<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

require('db.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Lê o e-mail recebido via JSON
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? null;

if (!$email) {
  echo json_encode(['success' => false, 'message' => 'E-mail não informado.']);
  exit;
}

// 🔹 Busca o certificado (PDF BLOB) associado ao e-mail
$sql = "
  SELECT 
      u.nome AS nome_usuario,
      u.email AS email_usuario,
      c.codigo AS codigo_certificado,
      c.template AS pdf_blob
  FROM Usuario u
  INNER JOIN Certificado c ON c.fk_Usuario_ID = u.ID
  WHERE u.email = ?
  LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$dado = $result->fetch_assoc();

if (!$dado) {
  echo json_encode(['success' => false, 'message' => 'Certificado não encontrado para este e-mail.']);
  exit;
}

if (empty($dado['pdf_blob'])) {
  echo json_encode(['success' => false, 'message' => 'Certificado não possui arquivo PDF (campo template vazio).']);
  exit;
}

// 🔹 Cria arquivo temporário do PDF
$tempFilePath = sys_get_temp_dir() . "/certificado_" . $dado['codigo_certificado'] . ".pdf";

// 🔹 Caso o conteúdo esteja em base64, decodifica antes de salvar
$pdfData = $dado['pdf_blob'];
if (base64_encode(base64_decode($pdfData, true)) === $pdfData) {
  $pdfData = base64_decode($pdfData);
}

file_put_contents($tempFilePath, $pdfData);

try {
  // Configuração do PHPMailer
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = 'smtp.gmail.com';
  $mail->SMTPAuth = true;
  $mail->Username = 'lukasjuliuswolf@gmail.com';
  $mail->Password = 'khin fxqc ohye rewu'; // senha de app
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = 587;

  // E-mail
  $mail->setFrom('lukasjuliuswolf@gmail.com', 'SICAD - Certificados');
  $mail->addAddress($dado['email_usuario'], $dado['nome_usuario']);
  $mail->Subject = 'Seu certificado está disponível!';
  $mail->Body = "Olá {$dado['nome_usuario']},\n\nSegue em anexo o certificado referente à sua participação.\n\nAtenciosamente,\nEquipe SICAD";
  $mail->addAttachment($tempFilePath, "certificado.pdf");

  // Envia
  $mail->send();

  // Atualiza status de envio
  $update = $conn->prepare("UPDATE Certificado SET status_envio = 'enviado' WHERE codigo = ?");
  $update->bind_param("i", $dado['codigo_certificado']);
  $update->execute();

  echo json_encode(['success' => true, 'message' => 'Certificado enviado com sucesso!']);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => 'Erro ao enviar e-mail: ' . $mail->ErrorInfo]);
} finally {
  if (file_exists($tempFilePath)) {
    unlink($tempFilePath); // Remove o arquivo temporário
  }
  $stmt->close();
  if (isset($update)) $update->close();
  $conn->close();
}
?>
