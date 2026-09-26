<?php

declare(strict_types=1);

/**
 * POST: {"email": "participante@example.com", "event_id": 123}
 * Seleção por e-mail: não recebe ID de usuário nem exige token local do SICAD.
 * O e-mail não autentica o solicitante; proteger na integração com o app de origem.
 * Endpoint novo: não substitui get_atividades_sicad_mobile.php.
 */
ob_start();
ini_set('display_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/certificados_acesso.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST, OPTIONS');
    sc_responder(405, ['success' => false, 'message' => 'Método não permitido. Use POST.']);
}

try {
    $input = sc_ler_json();
    sc_rejeitar_ids_usuario($input);
    $email = sc_email($input['email'] ?? null);
    $eventoId = sc_id($input['event_id'] ?? null, 'event_id');
    $conn = sc_conectar();

    $stmt = $conn->prepare(
    'SELECT DISTINCT
        a.ID,
        COALESCE(a.nome, \'Atividade\') AS nome,

        EXISTS(
            SELECT 1
            FROM certificado c
            WHERE c.fk_Atividade_ID = a.ID
              AND c.fk_Usuario_ID = u.ID
        ) AS emitido,

        EXISTS(
            SELECT 1
            FROM templatecertificado t
            WHERE t.fk_Atividade_ID = a.ID
        ) AS modelo

     FROM usuario u

     INNER JOIN atividade a
         ON a.fk_Evento_codigo = ?

     WHERE LOWER(TRIM(u.email)) = LOWER(TRIM(?))

       AND (
            EXISTS(
                SELECT 1
                FROM participa p
                WHERE p.fk_Atividade_ID = a.ID
                  AND p.fk_Usuario_ID = u.ID
                  AND p.presenca = 1
            )

            OR

            EXISTS(
                SELECT 1
                FROM certificado c
                WHERE c.fk_Atividade_ID = a.ID
                  AND c.fk_Usuario_ID = u.ID
            )
       )

     ORDER BY a.nome ASC, a.ID ASC'
);
    $stmt->bind_param('is', $eventoId, $email);
    $stmt->execute();
    $stmt->bind_result($id, $nome, $emitido, $modelo);
    $atividades = [];
    while ($stmt->fetch()) {
        $disponivel = (int) $emitido === 1 && (int) $modelo === 1;
        $atividades[] = [
            'id' => (int) $id,
            'nome' => (string) $nome,
            'certificado_disponivel' => $disponivel,
            // Disponibilidade indica registro + modelo; a renderização ainda é validada ao baixar.
            'motivo_indisponivel' => (int) $emitido !== 1
                ? 'Certificado ainda não emitido.'
                : ((int) $modelo !== 1 ? 'Modelo do certificado ainda não configurado.' : null),
        ];
    }
    $stmt->close();
    sc_responder(200, ['success' => true, 'atividades' => $atividades]);
} catch (SicadCertificadoHttpError $e) {
    sc_responder($e->getCode(), ['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('get_minhas_atividades_certificado.php: ' . $e->getMessage());
    sc_responder(500, ['success' => false, 'message' => 'Não foi possível carregar as atividades.']);
}
