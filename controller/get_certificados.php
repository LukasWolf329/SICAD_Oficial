<?php
    require("db.php");

    $sql = "SELECT * from vw_get_certificados";
    $result = $conn->query($sql);
    $certificados = [];

    if($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $status_num = ($row["status_envio"] === "nao_enviado") ? 1 : 0;
            $certificados = [
                "participante" => $row["participante"],
                "email" => $row["email"],
                "status_envio" => $status_num
            ];
        }
        echo json_encode($certificados, JSON_UNESCAPED_UNICODE);
    }
    else {
        echo json_encode(["message" => "Nenhum certificado encontrado"]);
    }
?>