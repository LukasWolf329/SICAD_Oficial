<?php
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Content-Type: application/json; charset=UTF-8");
    require('db.php');
    $response = [];

    $stmt=$conn->prepare("SELECT nome_atividade AS titulo from certificado");
    if($stmt->execute()) {
        $stmt->bind_result($titulo);

        while($stmt->fetch()) {
            $response[] = [
                "titulo" => $titulo
            ];
        }
    }

    $stmt->close();
    $conn->close();

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>