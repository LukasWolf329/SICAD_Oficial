<?php
include '../conexao.php';

header('Content-Type: application/json');

if(!isset($_POST['atividade_id'])){
    echo json_encode(["status" => "erro", "msg" => "atividade_id não enviado"]);
    exit;
}

$atividade_id = intval($_POST['atividade_id']);

if(isset($_FILES['arquivo'])) {

    $file = fopen($_FILES['arquivo']['tmp_name'], 'r');

    if(!$file){
        echo json_encode(["status" => "erro", "msg" => "Erro ao abrir arquivo"]);
        exit;
    }

    fgetcsv($file); // pula cabeçalho

    while(($data = fgetcsv($file)) !== FALSE) {

        $nome = $data[0] ?? '';
        $email = $data[1] ?? '';
        $cpf = $data[2] ?? '';
        $telefone = $data[3] ?? '';

        if(empty($email)) continue;

        $sqlCheck = "SELECT ID FROM Usuario WHERE email = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param("s", $email);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if($resultCheck->num_rows > 0){
            $user = $resultCheck->fetch_assoc();
            $user_id = $user['ID'];
        } else {

            $senha_padrao = password_hash("123456", PASSWORD_DEFAULT);

            $sqlInsert = "INSERT INTO Usuario (nome, email, senha, cpf, telefone)
                          VALUES (?, ?, ?, ?, ?)";

            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->bind_param("sssss", $nome, $email, $senha_padrao, $cpf, $telefone);
            $stmtInsert->execute();

            $user_id = $stmtInsert->insert_id;
        }

        $sqlParticipa = "INSERT IGNORE INTO Participa (fk_Usuario_ID, fk_Atividade_ID)
                         VALUES (?, ?)";

        $stmtParticipa = $conn->prepare($sqlParticipa);
        $stmtParticipa->bind_param("ii", $user_id, $atividade_id);
        $stmtParticipa->execute();
    }

    fclose($file);

    echo json_encode(["status" => "ok"]);
    exit;
}

echo json_encode(["status" => "erro", "msg" => "Arquivo não enviado"]);
?>