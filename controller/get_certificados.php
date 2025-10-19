<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");
require("db.php");

$sql = "SELECT * FROM vw_certificados_usuarios";
$result = $conn->query($sql);
$certificados = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $status_num = ($row["status_envio"] === "nao_enviado") ? 0 : 1;
        $certificados[] = [
            "participante" => $row["nome_usuario"],
            "email" => $row["email_usuario"],
            "status" => $status_num
        ];
    }

    echo json_encode($certificados, JSON_UNESCAPED_UNICODE);
}else {
    echo json_encode([]);
}

$conn->close();
?>