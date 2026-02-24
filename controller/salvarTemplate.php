<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

// NÃO mostrar erros em HTML (isso quebra o JSON)
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);

// transforma warnings/notices em exceção -> sempre JSON
set_error_handler(function ($severity, $message, $file, $line) {
  throw new ErrorException($message, 0, $severity, $file, $line);
});

require('db.php');

try {
  $data = json_decode(file_get_contents("php://input"), true);

  if (!isset($data['json'], $data['atividade_id'])) {
    echo json_encode([
      "success" => false,
      "message" => "Dados incompletos. 'json' e 'atividade_id' são obrigatórios."
    ]);
    exit();
  }

  $json = $data['json'];
  $atividade_id = (int) $data['atividade_id'];

  $imagem_src = $data['imagem_src'] ?? null;
  $imagem_preview = null;

  if (is_string($imagem_src) && $imagem_src !== "") {
    $decoded = urldecode($imagem_src);

    if (strpos($decoded, '/SICAD/assets/certificados/') !== false) {
      $imagem_preview = substr(
        $decoded,
        strpos($decoded, '/SICAD/assets/certificados/')
      );
    } elseif (strpos($decoded, '/assets/certificados/') !== false) {
      $imagem_preview = '/SICAD' . substr(
        $decoded,
        strpos($decoded, '/assets/certificados/')
      );
    } else {
      throw new Exception("Caminho da imagem inválido");
    }
  }


  // Verifica se já existe template para essa atividade
  $sqlCheck = "SELECT id FROM templatecertificado WHERE fk_Atividade_ID = ?";
  $stmtCheck = $conn->prepare($sqlCheck);
  $stmtCheck->bind_param("i", $atividade_id);
  $stmtCheck->execute();
  $resultCheck = $stmtCheck->get_result();

  if ($resultCheck->num_rows > 0) {
    if ($imagem_preview !== null) {
      // Atualiza JSON + imagem
      $sql = "UPDATE templatecertificado 
            SET json = ?, imagem_preview = ?
            WHERE fk_Atividade_ID = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ssi", $json, $imagem_preview, $atividade_id);
    } else {
      // Atualiza SOMENTE o JSON (não apaga a imagem)
      $sql = "UPDATE templatecertificado 
            SET json = ?
            WHERE fk_Atividade_ID = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("si", $json, $atividade_id);
    }
  } else {
    if ($imagem_preview === null) {
      throw new Exception("Selecione um modelo de certificado antes de salvar.");
    }

    $sql = "INSERT INTO templatecertificado 
          (nome, json, imagem_preview, fk_Atividade_ID, data_criacao)
          VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $nome = 'Template atividade ' . $atividade_id;
    $stmt->bind_param("sssi", $nome, $json, $imagem_preview, $atividade_id);
  }

  if ($stmt->execute()) {
    echo json_encode([
      "success" => true,
      "atividade_id" => $atividade_id,
      "imagem_preview" => $imagem_preview,
      "message" => "Template salvo com sucesso."
    ]);
  } else {
    echo json_encode([
      "success" => false,
      "message" => "Erro ao salvar no banco: " . $stmt->error
    ]);
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "success" => false,
    "message" => $e->getMessage()
  ]);
} finally {
  if (isset($stmtCheck) && $stmtCheck)
    $stmtCheck->close();
  if (isset($stmt) && $stmt)
    $stmt->close();
  if (isset($conn) && $conn)
    $conn->close();
}
