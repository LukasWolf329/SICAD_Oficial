<?php
    include 'conexao.php';

    $atividade_id = $_GET['atividade_id'];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=participantes.csv');

    $output = fopen('php://output', 'w');

    fputcsv($output, ['ID', 'Nome', 'Email', 'CPF', 'Telefone']);

    $sql = "SELECT u.ID, u.nome, u.email, u.cpf, u.telefone
            FROM Usuario u
            JOIN Participa p ON u.ID = p.fk_Usuario_ID
            WHERE p.fk_Atividade_ID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $atividade_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }

    fclose($output);
    setShowActions(false);
    exit;
?>