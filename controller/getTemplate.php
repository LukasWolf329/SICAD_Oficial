<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require('db.php');

if (isset($_GET['atividade_id'])) {
    $atividade_id = (int) $_GET['atividade_id'];

    $sql = "SELECT json, imagem_preview FROM templatecertificado WHERE fk_Atividade_ID = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $atividade_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (!empty($row['json'])) {
                echo json_encode([
                    "success" => true,
                    "template" => [
                        "json" => $row['json'],
                        "imagem_preview" => $row['imagem_preview']
                    ]
                ]);
            } else {
                echo json_encode([
                    "success" => true,
                    "template" => null,
                    "message" => "Nenhum template salvo ainda."
                ]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Atividade não encontrada."]);
        }
        $stmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "Erro na query: " . $conn->error]);
    }
} else {
    echo json_encode(["success" => false, "message" => "ID da atividade não fornecido."]);
}

$conn->close();
