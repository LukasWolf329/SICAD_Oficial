<?php
header("Access-Control-Allow-Origin: http://localhost:8081","https://sicad.linceonline.com.br");
header("Access-Control-Allow-Origin: https://sicad.linceonline.com.br");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . "/db.php";

header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

error_log("=== INICIO IMPORTACAO ===");

if(!isset($_POST['evento_id'])){
    error_log("evento_id nao enviado");
    echo json_encode(["status" => "erro", "msg" => "evento_id nao enviado"]);
    exit;
}

$evento_id = intval($_POST['evento_id']);
error_log("Evento ID: " . $evento_id);

if(!isset($_FILES['arquivo'])){
    error_log("Arquivo nao enviado");
    echo json_encode(["status" => "erro", "msg" => "Arquivo nao enviado"]);
    exit;
}

$tmp = $_FILES['arquivo']['tmp_name'];
error_log("Arquivo tmp: " . $tmp);

$file = fopen($tmp, 'r');

if(!$file){
    error_log("Erro ao abrir arquivo");
    echo json_encode(["status" => "erro", "msg" => "Erro ao abrir arquivo"]);
    exit;
}

$cabecalho = fgetcsv($file);
error_log("Cabecalho lido: " . implode(", ", $cabecalho));

$linha = 0;

while(($data = fgetcsv($file)) !== FALSE) {

    $linha++;
    error_log("Processando linha: " . $linha);

    $nome = $data[0] ?? '';
    $senha = $data[1] ?? '';
    $email = $data[2] ?? '';

    error_log("Nome: $nome | Email: $email");

    if(empty($email)) {
        error_log("Email vazio - pulando");
        continue;
    }

    $sqlCheck = "SELECT ID FROM usuario WHERE email = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("s", $email);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if($resultCheck->num_rows > 0){
        $user = $resultCheck->fetch_assoc();
        $user_id = $user['ID'];
        error_log("Usuario ja existe ID: " . $user_id);
    } else {

        $senha_hash = password_hash($senha ?: "123456", PASSWORD_DEFAULT);

        $sqlInsert = "INSERT INTO usuario (nome, email, senha)
                      VALUES (?, ?, ?)";

        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->bind_param("sss", $nome, $email, $senha_hash);
        
        if(!$stmtInsert->execute()){
            error_log("Erro ao inserir usuario: " . $stmtInsert->error);
            continue;
        }

        $user_id = $stmtInsert->insert_id;
        error_log("Usuario criado ID: " . $user_id);
    }

    // Buscar atividades do evento
    $sqlAtv = "SELECT ID FROM atividade WHERE fk_Evento_codigo = ?";
    $stmtAtv = $conn->prepare($sqlAtv);
    $stmtAtv->bind_param("i", $evento_id);
    $stmtAtv->execute();
    $resultAtv = $stmtAtv->get_result();

    while($atv = $resultAtv->fetch_assoc()){
        $atividade_id = $atv['ID'];

        $sqlParticipa = "INSERT IGNORE INTO participa (fk_Usuario_ID, fk_Atividade_ID)
                         VALUES (?, ?)";

        $stmtParticipa = $conn->prepare($sqlParticipa);
        $stmtParticipa->bind_param("ii", $user_id, $atividade_id);
        $stmtParticipa->execute();
    }
}

fclose($file);

error_log("=== FIM IMPORTACAO ===");

echo json_encode(["status" => "ok"]);
exit;
?>  