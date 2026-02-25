<?php
require_once __DIR__ . '/db.php';

if (!isset($_GET['evento_id'])) {
    die("evento_id não enviado");
}

$evento_id = intval($_GET['evento_id']);


// =============================
// Buscar nome do evento
// =============================

$sql_evento = "SELECT nome FROM evento WHERE codigo = ?";
$stmt_evento = $conn->prepare($sql_evento);
$stmt_evento->bind_param("i", $evento_id);
$stmt_evento->execute();
$result_evento = $stmt_evento->get_result();

if ($result_evento->num_rows === 0) {
    die("Evento não encontrado");
}

$evento = $result_evento->fetch_assoc();


// limpar nome para arquivo
$evento_nome = $evento['nome'];

// remove caracteres inválidos para nome de arquivo
$evento_nome_limpo = preg_replace('/[^a-zA-Z0-9\s_-]/', '', $evento_nome);


// =============================
// Headers CSV
// =============================

$filename = "participantes_evento_" . $evento_nome_limpo . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$output = fopen('php://output', 'w');


// =============================
// Cabeçalho CSV
// =============================

fputcsv($output, [
    'Nome',
    'Senha',
    'Email',
    'Total de Atividades',
    'Carga Horária Total',
    'Atividades Inscritas'
]);


// =============================
// Query principal
// =============================

$sql = "
SELECT 
    u.nome,
    u.senha,
    u.email,
    COUNT(a.ID) as total_atividades,
    COALESCE(SUM(a.carga_horaria),0) as carga_total,
    GROUP_CONCAT(a.nome ORDER BY a.nome SEPARATOR ', ') as atividades
FROM usuario u
JOIN participa p ON u.ID = p.fk_Usuario_ID
JOIN atividade a ON p.fk_Atividade_ID = a.ID
WHERE a.fk_Evento_codigo = ?
GROUP BY u.ID
ORDER BY u.nome ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $evento_id);
$stmt->execute();

$result = $stmt->get_result();


// =============================
// Escrever dados
// =============================

while ($row = $result->fetch_assoc()) {

    fputcsv($output, [
        $row['nome'],
        $row['senha'],
        $row['email'],
        $row['total_atividades'],
        $row['carga_total'],
        $row['atividades']
    ]);

}


fclose($output);
exit;
?>