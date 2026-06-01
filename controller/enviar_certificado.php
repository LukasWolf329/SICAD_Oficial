<?php
declare(strict_types=1);

ob_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Expose-Headers: Content-Disposition, X-Certificado-Filename, X-Certificado-Status');
header('X-Enviar-Certificado-Version: sicad-2026-05-28-email-download-pdf');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(204);
    exit;
}

ini_set('display_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

function respond(int $status, array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    $payload['versao_enviar_certificado'] = 'sicad-2026-05-28-email-download-pdf';

    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    if ($json === false) {
        http_response_code(500);
        echo '{"success":false,"message":"Falha ao gerar JSON"}';
        exit;
    }

    echo $json;
    exit;
}

function enviar_pdf_para_download(string $pdfPath, string $filename, int $statusAtual): void
{
    if (!is_file($pdfPath)) {
        respond(500, [
            'success' => false,
            'message' => 'PDF não encontrado após geração'
        ]);
    }

    if (ob_get_length()) {
        ob_clean();
    }

    $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $filename) ?: 'certificado.pdf';

    http_response_code(200);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($pdfPath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Certificado-Filename: ' . $filename);
    header('X-Certificado-Status: ' . $statusAtual);

    readfile($pdfPath);
    @unlink($pdfPath);
    exit;
}

function preparar(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar SQL: ' . $conn->error . ' | SQL: ' . $sql);
    }

    return $stmt;
}

function executar(mysqli_stmt $stmt, string $rotulo = 'consulta'): void
{
    if (!$stmt->execute()) {
        throw new RuntimeException('Erro ao executar ' . $rotulo . ': ' . $stmt->error);
    }
}

function qi(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function colunas_tabela(mysqli $conn, string $tabela): array
{
    static $cache = [];
    $key = strtolower($tabela);

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = preparar($conn, 'SHOW COLUMNS FROM ' . qi($tabela));
    executar($stmt, 'listagem de colunas da tabela ' . $tabela);

    $field = null;
    $type = null;
    $null = null;
    $keyField = null;
    $default = null;
    $extra = null;

    $stmt->bind_result($field, $type, $null, $keyField, $default, $extra);

    $colunas = [];
    while ($stmt->fetch()) {
        $nome = (string) $field;
        $colunas[strtolower($nome)] = $nome;
    }

    $stmt->free_result();
    $stmt->close();

    $cache[$key] = $colunas;
    return $cache[$key];
}

function coluna_existe(mysqli $conn, string $tabela, string $coluna): bool
{
    $colunas = colunas_tabela($conn, $tabela);
    return isset($colunas[strtolower($coluna)]);
}

function gerar_codigo_validacao_unico(mysqli $conn): string
{
    for ($i = 0; $i < 20; $i++) {
        $codigo = strtoupper(bin2hex(random_bytes(12)));
        $stmt = preparar($conn, 'SELECT 1 FROM certificado WHERE codigo_validacao = ? LIMIT 1');
        $stmt->bind_param('s', $codigo);
        executar($stmt, 'consulta de código de validação');

        $existe = null;
        $stmt->bind_result($existe);
        $encontrou = $stmt->fetch();
        $stmt->free_result();
        $stmt->close();

        if (!$encontrou) {
            return $codigo;
        }
    }

    throw new RuntimeException('Não foi possível gerar código de validação único.');
}

function atualizar_status_certificado(mysqli $conn, int $certificadoCodigo, int $atividadeId): int
{
    if (coluna_existe($conn, 'certificado', 'status_envio')) {
        $stmt = preparar($conn, 'UPDATE certificado SET status_envio = 1 WHERE codigo = ?');
        $stmt->bind_param('i', $certificadoCodigo);
        executar($stmt, 'atualização de status do certificado');
        $stmt->close();

        $chk = preparar($conn, 'SELECT COALESCE(status_envio, 0) FROM certificado WHERE codigo = ? LIMIT 1');
        $chk->bind_param('i', $certificadoCodigo);
        executar($chk, 'consulta de status do certificado');
        $status = 0;
        $chk->bind_result($status);
        $chk->fetch();
        $chk->free_result();
        $chk->close();

        return (int) $status;
    }

    // Compatibilidade com o schema antigo, que tinha status_envio em atividade.
    // Isto não é por aluno, mas evita erro caso a coluna ainda não tenha sido movida para certificado.
    if (coluna_existe($conn, 'atividade', 'status_envio')) {
        $stmt = preparar($conn, 'UPDATE atividade SET status_envio = 1 WHERE ID = ?');
        $stmt->bind_param('i', $atividadeId);
        executar($stmt, 'atualização de status da atividade');
        $stmt->close();

        return 1;
    }

    return 1;
}

function pngHasTransparency(string $filePath): bool
{
    $fp = @fopen($filePath, 'rb');
    if (!$fp) {
        return false;
    }

    $signature = fread($fp, 8);
    if ($signature !== "\x89PNG\r\n\x1a\n") {
        fclose($fp);
        return false;
    }

    while (!feof($fp)) {
        $lenBytes = fread($fp, 4);
        if (strlen($lenBytes) !== 4) {
            break;
        }

        $length = unpack('N', $lenBytes)[1];
        $type = fread($fp, 4);
        if (strlen($type) !== 4) {
            break;
        }

        if ($type === 'IHDR') {
            $data = fread($fp, $length);
            if (strlen($data) >= 10) {
                $colorType = ord($data[9]);
                if ($colorType === 4 || $colorType === 6) {
                    fclose($fp);
                    return true;
                }
            }

            fread($fp, 4);
            continue;
        }

        if ($type === 'tRNS') {
            fclose($fp);
            return true;
        }

        fseek($fp, $length + 4, SEEK_CUR);

        if ($type === 'IEND') {
            break;
        }
    }

    fclose($fp);
    return false;
}

function detectar_extensao_imagem(string $binario): string
{
    if (strncmp($binario, "\xFF\xD8\xFF", 3) === 0) {
        return 'jpg';
    }

    if (strncmp($binario, "\x89PNG\r\n\x1a\n", 8) === 0) {
        return 'png';
    }

    if (strncmp($binario, 'GIF87a', 6) === 0 || strncmp($binario, 'GIF89a', 6) === 0) {
        return 'gif';
    }

    return 'png';
}

function caminho_local_por_web_path(string $p): string
{
    $p = str_replace('\\', '/', $p);

    if (preg_match('#^https?://#i', $p)) {
        $p = parse_url($p, PHP_URL_PATH) ?: '';
    }

    $p = preg_replace('#^(\.\./)+#', '', $p);
    $p = '/' . ltrim($p, '/');

    $docroot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($docroot === '') {
        throw new RuntimeException('DOCUMENT_ROOT não encontrado para resolver imagem local.');
    }

    if (substr($docroot, -6) === '/SICAD' && strpos($p, '/SICAD/') === 0) {
        $p = substr($p, 6);
    }

    $full = $docroot . $p;
    $real = realpath($full);

    if ($real === false || !is_file($real)) {
        throw new RuntimeException("Imagem não encontrada. docroot={$docroot} webPath={$p} fullPath={$full}");
    }

    return $real;
}

function imagem_source_para_arquivo(string $src): array
{
    $srcOriginal = $src;
    $src = trim($src);

    if ($src === '') {
        throw new RuntimeException('Imagem sem origem.');
    }

    if (preg_match('#^data:image/([^;]+);base64,#i', $src, $m)) {
        $mime = strtolower($m[1]);
        $ext = $mime === 'jpeg' ? 'jpg' : ($mime === 'svg+xml' ? 'svg' : $mime);
        $commaPos = strpos($src, ',');

        if ($commaPos === false) {
            throw new RuntimeException('DataURL de imagem inválido.');
        }

        $bin = base64_decode(substr($src, $commaPos + 1), true);
        if ($bin === false) {
            throw new RuntimeException('Base64 de imagem inválido.');
        }

        $tmp = sys_get_temp_dir() . '/sicad_img_' . uniqid('', true) . '.' . $ext;
        file_put_contents($tmp, $bin);
        return [$tmp, strtoupper($ext), true];
    }

    // Caso a coluna BLOB tenha vindo como bytes da imagem, e não como path.
    if (strpos($srcOriginal, "\0") !== false || strncmp($srcOriginal, "\xFF\xD8\xFF", 3) === 0 || strncmp($srcOriginal, "\x89PNG\r\n\x1a\n", 8) === 0) {
        $ext = detectar_extensao_imagem($srcOriginal);
        $tmp = sys_get_temp_dir() . '/sicad_img_' . uniqid('', true) . '.' . $ext;
        file_put_contents($tmp, $srcOriginal);
        return [$tmp, strtoupper($ext), true];
    }

    if (preg_match('#^https?://#i', $src)) {
        try {
            $local = caminho_local_por_web_path($src);
            $ext = strtolower(pathinfo($local, PATHINFO_EXTENSION) ?: 'png');
            return [$local, strtoupper($ext === 'jpeg' ? 'jpg' : $ext), false];
        } catch (Throwable $e) {
            $bin = @file_get_contents($src);
            if ($bin === false) {
                throw new RuntimeException('Não consegui localizar nem baixar imagem: ' . $src);
            }

            $ext = detectar_extensao_imagem($bin);
            $tmp = sys_get_temp_dir() . '/sicad_img_' . uniqid('', true) . '.' . $ext;
            file_put_contents($tmp, $bin);
            return [$tmp, strtoupper($ext), true];
        }
    }

    $local = caminho_local_por_web_path($src);
    $ext = strtolower(pathinfo($local, PATHINFO_EXTENSION) ?: 'png');
    return [$local, strtoupper($ext === 'jpeg' ? 'jpg' : $ext), false];
}

function corrigir_strings_problematicas_template(string $json): string
{
    $json = preg_replace('/"textBaseline"\s*:\s*"alphabetical"/i', '"textBaseline":"alphabetic"', $json) ?? $json;
    $json = preg_replace('/\\"textBaseline\\"\s*:\s*\\"alphabetical\\"/i', '\\"textBaseline\\":\\"alphabetic\\"', $json) ?? $json;
    return str_ireplace('alphabetical', 'alphabetic', $json);
}

function sanitizar_template_fabric(&$valor): void
{
    if (!is_array($valor)) {
        return;
    }

    if (array_key_exists('textBaseline', $valor)) {
        $baseline = strtolower(trim((string) $valor['textBaseline']));
        $permitidos = ['top', 'hanging', 'middle', 'alphabetic', 'ideographic', 'bottom'];
        $valor['textBaseline'] = in_array($baseline, $permitidos, true) ? $baseline : 'alphabetic';
    }

    if (array_key_exists('crossOrigin', $valor)) {
        unset($valor['crossOrigin']);
    }

    if (array_key_exists('sicadOriginalText', $valor)) {
        unset($valor['sicadOriginalText']);
    }

    if (array_key_exists('sicadPreviewOnly', $valor)) {
        unset($valor['sicadPreviewOnly']);
    }

    foreach ($valor as &$item) {
        if (is_string($item) && strtolower($item) === 'alphabetical') {
            $item = 'alphabetic';
        } elseif (is_array($item)) {
            sanitizar_template_fabric($item);
        }
    }
}

function decode_json_safe($raw): ?array
{
    if (!is_string($raw)) {
        return null;
    }

    $valor = $raw;

    for ($i = 0; $i < 4; $i++) {
        if (is_string($valor)) {
            $valor = corrigir_strings_problematicas_template(trim($valor));
            $decoded = json_decode($valor, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = json_decode(stripslashes($valor), true);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            $valor = $decoded;
            continue;
        }

        if (is_array($valor) && isset($valor['json']) && is_string($valor['json'])) {
            $valor = $valor['json'];
            continue;
        }

        break;
    }

    if (!is_array($valor)) {
        return null;
    }

    sanitizar_template_fabric($valor);
    return $valor;
}


function objeto_e_modelo_fundo(array $obj): bool
{
    return (($obj['dataTipo'] ?? '') === 'modelo_fundo') || !empty($obj['sicadBackground']);
}

function encontrar_modelo_fundo_no_template(array $template): ?array
{
    $objects = is_array($template['objects'] ?? null) ? $template['objects'] : [];

    foreach ($objects as $obj) {
        if (is_array($obj) && objeto_e_modelo_fundo($obj)) {
            return $obj;
        }
    }

    return null;
}

function parse_color($c): array
{
    $c = trim((string) $c);
    if ($c === '' || strtolower($c) === 'transparent' || strtolower($c) === 'none') {
        return [0, 0, 0, 0];
    }

    if ($c[0] === '#') {
        $hex = ltrim($c, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) === 6) {
            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), 1];
        }
    }

    if (preg_match('/rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([0-9.]+)\s*)?\)/i', $c, $m)) {
        $a = isset($m[4]) ? (float) $m[4] : 1;
        return [min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3]), max(0, min(1, $a))];
    }

    return [0, 0, 0, 1];
}

function align_fabric_to_tcpdf($a): string
{
    $a = strtolower((string) $a);
    if ($a === 'center') {
        return 'C';
    }
    if ($a === 'right') {
        return 'R';
    }
    if ($a === 'justify') {
        return 'J';
    }
    return 'L';
}

function replace_placeholders($text, array $map): string
{
    return preg_replace_callback('/\{\{\s*([A-Z0-9_]+)\s*\}\}/i', function ($m) use ($map) {
        $k = strtoupper($m[1]);
        return array_key_exists($k, $map) ? (string) $map[$k] : '';
    }, (string) $text);
}

function texto_contem_tag_dinamica($text): bool
{
    return preg_match('/\{\{\s*[A-Z0-9_]+\s*\}\}/i', (string) $text) === 1;
}

function texto_e_apenas_tag_dinamica($text): bool
{
    return preg_match('/^\s*\{\{\s*[A-Z0-9_]+\s*\}\}\s*$/i', (string) $text) === 1;
}

function objeto_texto_e_tag_dinamica(array $obj): bool
{
    $type = $obj['type'] ?? '';

    if (!in_array($type, ['i-text', 'text', 'textbox'], true)) {
        return false;
    }

    return texto_e_apenas_tag_dinamica($obj['text'] ?? '');
}

function ordenar_objetos_pdf_sem_sobrepor_tags(array $objects): array
{
    $normais = [];
    $tags = [];

    foreach ($objects as $obj) {
        if (!is_array($obj)) {
            continue;
        }

        /*
         * Tags puras como {{NOME}}, {{EMAIL}}, {{ATIVIDADE}} etc.
         * serão renderizadas por último. Assim, nenhum texto comum
         * fica desenhado por cima do nome do usuário após a substituição.
         */
        if (objeto_texto_e_tag_dinamica($obj)) {
            $tags[] = $obj;
        } else {
            $normais[] = $obj;
        }
    }

    return array_merge($normais, $tags);
}

function formatar_codigo_validacao(string $codigo): string
{
    $limpo = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($codigo));
    return implode('-', str_split($limpo, 4));
}

function formatar_data_br($valor): string
{
    if ($valor === null || $valor === '') {
        return '';
    }

    try {
        return (new DateTime((string) $valor))->format('d/m/Y');
    } catch (Throwable $e) {
        return (string) $valor;
    }
}

function nome_arquivo_seguro(string $valor): string
{
    $valor = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor) ?: $valor;
    $valor = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $valor);
    $valor = trim((string) $valor, '_');
    return $valor !== '' ? substr($valor, 0, 120) : 'certificado';
}

function mat_mul(array $m1, array $m2): array
{
    return [
        $m1[0] * $m2[0] + $m1[2] * $m2[1],
        $m1[1] * $m2[0] + $m1[3] * $m2[1],
        $m1[0] * $m2[2] + $m1[2] * $m2[3],
        $m1[1] * $m2[2] + $m1[3] * $m2[3],
        $m1[0] * $m2[4] + $m1[2] * $m2[5] + $m1[4],
        $m1[1] * $m2[4] + $m1[3] * $m2[5] + $m1[5],
    ];
}

function mat_apply(array $m, float $x, float $y): array
{
    return [$m[0] * $x + $m[2] * $y + $m[4], $m[1] * $x + $m[3] * $y + $m[5]];
}

function get_obj_wh(array $obj): array
{
    $type = $obj['type'] ?? '';

    if ($type === 'circle' && isset($obj['radius'])) {
        $d = (float) $obj['radius'] * 2.0;
        return [(float) ($obj['width'] ?? $d), (float) ($obj['height'] ?? $d)];
    }

    $width = (float) ($obj['sicadBoxWidth'] ?? $obj['width'] ?? 0);
    $height = (float) ($obj['sicadBoxHeight'] ?? $obj['boxHeight'] ?? $obj['height'] ?? 0);

    return [$width, $height];
}

function obj_matrix(array $obj): array
{
    [$w, $h] = get_obj_wh($obj);

    $sx0 = (float) ($obj['scaleX'] ?? 1);
    $sy0 = (float) ($obj['scaleY'] ?? 1);

    $sx = (!empty($obj['flipX']) ? -1 : 1) * $sx0;
    $sy = (!empty($obj['flipY']) ? -1 : 1) * $sy0;

    $angle = deg2rad((float) ($obj['angle'] ?? 0));
    $cos = cos($angle);
    $sin = sin($angle);

    $originX = strtolower((string) ($obj['originX'] ?? 'left'));
    $originY = strtolower((string) ($obj['originY'] ?? 'top'));
    $left = (float) ($obj['left'] ?? 0);
    $top = (float) ($obj['top'] ?? 0);

    $scaledW = abs($w * $sx0);
    $scaledH = abs($h * $sy0);

    $cx = $left;
    if ($originX === 'left') {
        $cx = $left + $scaledW / 2;
    } elseif ($originX === 'right') {
        $cx = $left - $scaledW / 2;
    }

    $cy = $top;
    if ($originY === 'top') {
        $cy = $top + $scaledH / 2;
    } elseif ($originY === 'bottom') {
        $cy = $top - $scaledH / 2;
    }

    return [$cos * $sx, $sin * $sx, -$sin * $sy, $cos * $sy, $cx, $cy];
}

function px_to_mm(float $xPx, float $yPx, float $scale, float $offX, float $offY): array
{
    return [$offX + $xPx * $scale, $offY + $yPx * $scale];
}

function style_from_obj(array $obj, float $scale): array
{
    [$sr, $sg, $sb, $sa] = parse_color($obj['stroke'] ?? '');
    $opacity = (float) ($obj['opacity'] ?? 1);
    $sa *= $opacity;

    $strokeWpx = (float) ($obj['strokeWidth'] ?? 0);
    $sxAbs = abs((float) ($obj['scaleX'] ?? 1));
    $syAbs = abs((float) ($obj['scaleY'] ?? 1));
    $strokeScale = max(0.0001, ($sxAbs + $syAbs) / 2);

    $lineStyle = null;
    if ($strokeWpx > 0 && $sa > 0.001 && ($obj['stroke'] ?? '') !== '') {
        $lineStyle = [
            'width' => max(0.05, $strokeWpx * $strokeScale * $scale),
            'cap' => 'butt',
            'join' => 'miter',
            'color' => [$sr, $sg, $sb],
        ];

        if (!empty($obj['strokeDashArray']) && is_array($obj['strokeDashArray'])) {
            $dashMm = array_map(function ($v) use ($scale, $strokeScale) {
                return max(0.05, (float) $v * $strokeScale * $scale);
            }, $obj['strokeDashArray']);
            $lineStyle['dash'] = implode(',', $dashMm);
        }
    }

    [$fr, $fg, $fb, $fa] = parse_color($obj['fill'] ?? '');
    $fa *= $opacity;

    $fillColor = null;
    if ($fa > 0.001 && ($obj['fill'] ?? '') !== '') {
        $fillColor = [$fr, $fg, $fb];
    }

    $alpha = max($sa, $fa);
    return [$lineStyle, $fillColor, $alpha];
}

function estilo_fonte(array $obj): string
{
    $style = '';
    $fontWeight = strtolower((string) ($obj['fontWeight'] ?? ''));
    if ($fontWeight === 'bold' || (is_numeric($fontWeight) && (int) $fontWeight >= 600)) {
        $style .= 'B';
    }
    if (strtolower((string) ($obj['fontStyle'] ?? '')) === 'italic') {
        $style .= 'I';
    }
    if (!empty($obj['underline'])) {
        $style .= 'U';
    }
    return $style;
}

function fonte_tcpdf(array $obj): string
{
    $ff = strtolower((string) ($obj['fontFamily'] ?? ''));
    if (strpos($ff, 'times') !== false || strpos($ff, 'georgia') !== false) {
        return 'times';
    }
    if (strpos($ff, 'courier') !== false || strpos($ff, 'mono') !== false) {
        return 'courier';
    }
    if (strpos($ff, 'arial') !== false || strpos($ff, 'verdana') !== false || strpos($ff, 'helvetica') !== false) {
        return 'helvetica';
    }
    return 'dejavusans';
}

function largura_max_linhas(TCPDF $pdf, string $text): float
{
    $linhas = preg_split("/\r\n|\r|\n/", $text) ?: [''];
    $max = 0.0;

    foreach ($linhas as $linha) {
        $max = max($max, $pdf->GetStringWidth($linha));
    }

    return $max;
}

function linhas_quebradas_estimadas(TCPDF $pdf, string $text, float $maxWidth): int
{
    $explicitLines = preg_split("/\r\n|\r|\n/", $text) ?: [''];
    $count = 0;

    foreach ($explicitLines as $line) {
        $line = trim((string) $line);

        if ($line === '') {
            $count++;
            continue;
        }

        $words = preg_split('/\s+/', $line) ?: [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if ($pdf->GetStringWidth($candidate) <= $maxWidth || $current === '') {
                $current = $candidate;
                continue;
            }

            $count++;
            $current = $word;
        }

        $count++;
    }

    return max(1, $count);
}

function ajustar_fonte_textbox(TCPDF $pdf, string $text, float $fontPt, float $minPt, float $cellW, float $cellH, float $lineHeight, bool $semQuebra): float
{
    $fontPt = max($minPt, $fontPt);

    for ($pt = $fontPt; $pt >= $minPt; $pt -= 0.5) {
        $pdf->SetFontSize($pt);

        if ($semQuebra) {
            $linhas = preg_split("/\r\n|\r|\n/", $text) ?: [''];
            $altura = count($linhas) * $pt * 0.3527777778 * $lineHeight;
            if (largura_max_linhas($pdf, $text) <= $cellW && $altura <= $cellH) {
                return $pt;
            }
            continue;
        }

        $qtdLinhas = linhas_quebradas_estimadas($pdf, $text, $cellW);
        $altura = $qtdLinhas * $pt * 0.3527777778 * $lineHeight;

        if ($altura <= $cellH) {
            return $pt;
        }
    }

    return $minPt;
}

function render_fabric_obj(TCPDF $pdf, array $obj, array $parentM, float $scale, float $offX, float $offY, array $map): void
{
    $type = $obj['type'] ?? '';

    if (objeto_e_modelo_fundo($obj)) {
        return;
    }

    if (!empty($obj['excludeFromExport']) || !empty($obj['sicadPreviewOnly'])) {
        return;
    }

    if ($type === 'group') {
        $m = mat_mul($parentM, obj_matrix($obj));
        $children = $obj['objects'] ?? [];
        if (is_array($children)) {
            foreach ($children as $child) {
                if (is_array($child)) {
                    render_fabric_obj($pdf, $child, $m, $scale, $offX, $offY, $map);
                }
            }
        }
        return;
    }

    $m = mat_mul($parentM, obj_matrix($obj));
    [$lineStyle, $fillColor, $alpha] = style_from_obj($obj, $scale);

    $setAlpha = method_exists($pdf, 'SetAlpha');
    if ($setAlpha) {
        $pdf->SetAlpha(max(0, min(1, $alpha)));
    }

    if (in_array($type, ['i-text', 'text', 'textbox'], true)) {
        $raw = (string) ($obj['text'] ?? '');
        if ($raw === '') {
            if ($setAlpha) {
                $pdf->SetAlpha(1);
            }
            return;
        }

        $temTagDinamica = texto_contem_tag_dinamica($raw);
        $apenasTagDinamica = texto_e_apenas_tag_dinamica($raw);

        $text = $temTagDinamica ? replace_placeholders($raw, $map) : $raw;

        [$w, $h] = get_obj_wh($obj);
        $sxAbs = abs((float) ($obj['scaleX'] ?? 1));
        $syAbs = abs((float) ($obj['scaleY'] ?? 1));
        $scaledW = max(1.0, $w * $sxAbs);
        $scaledH = max(1.0, $h * $syAbs);

        $originX = strtolower((string) ($obj['originX'] ?? 'left'));
        $originY = strtolower((string) ($obj['originY'] ?? 'top'));
        $left = (float) ($obj['left'] ?? 0);
        $top = (float) ($obj['top'] ?? 0);

        $xPx = $left;
        if ($originX === 'center') {
            $xPx = $left - $scaledW / 2;
        } elseif ($originX === 'right') {
            $xPx = $left - $scaledW;
        }

        $yPx = $top;
        if ($originY === 'center') {
            $yPx = $top - $scaledH / 2;
        } elseif ($originY === 'bottom') {
            $yPx = $top - $scaledH;
        }

        [$xMm, $yMm] = px_to_mm($xPx, $yPx, $scale, $offX, $offY);

        $fontSizePx = (float) ($obj['fontSize'] ?? 16) * $syAbs;
        $fontPt = max(4, ($fontSizePx * $scale) * 72.0 / 25.4);
        $minFontPt = max(3, ((float) ($obj['minFontSize'] ?? 8) * $scale) * 72.0 / 25.4);

        $style = estilo_fonte($obj);
        $tcpdfFont = fonte_tcpdf($obj);

        [$r, $g, $b] = array_slice(parse_color($obj['fill'] ?? '#000'), 0, 3);
        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetFont($tcpdfFont, $style, $fontPt);

        if (method_exists($pdf, 'SetFontStretching')) {
            $stretch = ($syAbs > 0) ? (100.0 * ($sxAbs / $syAbs)) : 100.0;
            $pdf->SetFontStretching(max(10, min(300, $stretch)));
        }

        $lineHeight = (float) ($obj['lineHeight'] ?? 1.16);
        $align = align_fabric_to_tcpdf($obj['textAlign'] ?? 'left');

        $wMmFabric = $scaledW * $scale;
        $cellW = max(1.0, $wMmFabric);
        $cellH = max(($scaledH * $scale), (($fontSizePx * $lineHeight) * $scale));

        /*
         * Se o texto contém tag dinâmica, como {{NOME}}, {{EMAIL}},
         * {{ATIVIDADE}}, etc., ele NÃO pode expandir livremente a largura,
         * porque isso faz o nome do usuário invadir outro texto no PDF.
         *
         * Então:
         * - tag dinâmica usa autoFit obrigatório;
         * - tag pura, como {{NOME}}, tenta ficar em uma linha e encolher se precisar;
         * - texto comum continua com o comportamento antigo.
         */
        $autoFit = !empty($obj['autoFit']) || !empty($obj['sicadAutoFit']) || $temTagDinamica;
        $semQuebra = ($obj['autoFitMode'] ?? '') === 'shrink' || !empty($obj['sicadNoWrap']) || $apenasTagDinamica;

        if (!$temTagDinamica && $type !== 'textbox' && !$autoFit) {
            $maxLineW = largura_max_linhas($pdf, $text);
            $cellW = max($cellW, $maxLineW + 0.5);

            $delta = $cellW - $wMmFabric;
            if ($delta > 0.001) {
                if ($align === 'C') {
                    $xMm -= $delta / 2.0;
                } elseif ($align === 'R') {
                    $xMm -= $delta;
                }
            }
        }

        if ($autoFit) {
            if ($apenasTagDinamica) {
                $cellW = max($cellW, 35.0);
                $cellH = max($cellH, ($fontSizePx * $lineHeight) * $scale);
            }

            $fontPt = ajustar_fonte_textbox(
                $pdf,
                $text,
                $fontPt,
                $minFontPt,
                $cellW,
                $cellH,
                $lineHeight,
                $semQuebra
            );

            $pdf->SetFont($tcpdfFont, $style, $fontPt);
        }

        if (method_exists($pdf, 'setCellHeightRatio')) {
            $pdf->setCellHeightRatio(max(0.8, min(3.0, $lineHeight)));
        }

        $angle = (float) ($obj['angle'] ?? 0);
        $cxMm = $xMm + $cellW / 2;
        $cyMm = $yMm + $cellH / 2;

        if (abs($angle) > 0.001 && method_exists($pdf, 'StartTransform')) {
            $pdf->StartTransform();
            $pdf->Rotate($angle, $cxMm, $cyMm);
        }

        $pdf->SetXY($xMm, $yMm);
        $pdf->MultiCell(
            $cellW,
            max(0.1, $cellH),
            $text,
            0,
            $align,
            false,
            1,
            '',
            '',
            true,
            0,
            false,
            false
        );

        if (abs($angle) > 0.001 && method_exists($pdf, 'StopTransform')) {
            $pdf->StopTransform();
        }

        if (method_exists($pdf, 'SetFontStretching')) {
            $pdf->SetFontStretching(100);
        }
        if (method_exists($pdf, 'setCellHeightRatio')) {
            $pdf->setCellHeightRatio(1.25);
        }
        if ($setAlpha) {
            $pdf->SetAlpha(1);
        }
        return;
    }

    if ($type === 'image') {
        $src = (string) ($obj['src'] ?? '');
        if ($src === '') {
            if ($setAlpha) {
                $pdf->SetAlpha(1);
            }
            return;
        }

        $opacity = (float) ($obj['opacity'] ?? 1);
        if ($setAlpha) {
            $pdf->SetAlpha(max(0, min(1, $opacity)));
        }

        [$w, $h] = get_obj_wh($obj);
        $sxAbs = abs((float) ($obj['scaleX'] ?? 1));
        $syAbs = abs((float) ($obj['scaleY'] ?? 1));
        $scaledWpx = max(1.0, $w * $sxAbs);
        $scaledHpx = max(1.0, $h * $syAbs);

        $originX = strtolower((string) ($obj['originX'] ?? 'left'));
        $originY = strtolower((string) ($obj['originY'] ?? 'top'));
        $left = (float) ($obj['left'] ?? 0);
        $top = (float) ($obj['top'] ?? 0);

        $xPx = $left;
        if ($originX === 'center') {
            $xPx = $left - ($scaledWpx / 2);
        } elseif ($originX === 'right') {
            $xPx = $left - $scaledWpx;
        }

        $yPx = $top;
        if ($originY === 'center') {
            $yPx = $top - ($scaledHpx / 2);
        } elseif ($originY === 'bottom') {
            $yPx = $top - $scaledHpx;
        }

        [$xMm, $yMm] = px_to_mm($xPx, $yPx, $scale, $offX, $offY);
        $wMm = max(0.1, $scaledWpx * $scale);
        $hMm = max(0.1, $scaledHpx * $scale);
        $cxMm = $xMm + ($wMm / 2);
        $cyMm = $yMm + ($hMm / 2);

        [$imgFile, $imgType, $isTmp] = imagem_source_para_arquivo($src);
        if ($imgType === 'JPG') {
            $imgType = 'JPEG';
        }

        $angle = (float) ($obj['angle'] ?? 0);
        $flipX = !empty($obj['flipX']);
        $flipY = !empty($obj['flipY']);

        if (method_exists($pdf, 'StartTransform')) {
            $pdf->StartTransform();
            if ($flipX && method_exists($pdf, 'MirrorH')) {
                $pdf->MirrorH($cxMm);
            }
            if ($flipY && method_exists($pdf, 'MirrorV')) {
                $pdf->MirrorV($cyMm);
            }
            if (abs($angle) > 0.001 && method_exists($pdf, 'Rotate')) {
                $pdf->Rotate($angle, $cxMm, $cyMm);
            }
        }

        $pdf->Image($imgFile, $xMm, $yMm, $wMm, $hMm, $imgType);

        if (method_exists($pdf, 'StopTransform')) {
            $pdf->StopTransform();
        }
        if ($isTmp && is_file($imgFile)) {
            @unlink($imgFile);
        }
        if ($setAlpha) {
            $pdf->SetAlpha(1);
        }
        return;
    }

    if (in_array($type, ['rect', 'triangle', 'circle', 'line'], true)) {
        $hasStroke = is_array($lineStyle);
        $hasFill = is_array($fillColor);
        $drawStyle = ($hasStroke && $hasFill) ? 'DF' : ($hasFill ? 'F' : ($hasStroke ? 'D' : ''));

        if ($type === 'rect') {
            [$w, $h] = get_obj_wh($obj);
            $local = [[-$w / 2, -$h / 2], [$w / 2, -$h / 2], [$w / 2, $h / 2], [-$w / 2, $h / 2]];
            $flat = [];
            foreach ($local as $p) {
                $gp = mat_apply($m, $p[0], $p[1]);
                $flat[] = $offX + $gp[0] * $scale;
                $flat[] = $offY + $gp[1] * $scale;
            }
            $pdf->Polygon($flat, $drawStyle, $lineStyle ?? [], $fillColor ?? []);
            if ($setAlpha) {
                $pdf->SetAlpha(1);
            }
            return;
        }

        if ($type === 'triangle') {
            [$w, $h] = get_obj_wh($obj);
            $local = [[0, -$h / 2], [-$w / 2, $h / 2], [$w / 2, $h / 2]];
            $flat = [];
            foreach ($local as $p) {
                $gp = mat_apply($m, $p[0], $p[1]);
                $flat[] = $offX + $gp[0] * $scale;
                $flat[] = $offY + $gp[1] * $scale;
            }
            $pdf->Polygon($flat, $drawStyle, $lineStyle ?? [], $fillColor ?? []);
            if ($setAlpha) {
                $pdf->SetAlpha(1);
            }
            return;
        }

        if ($type === 'circle') {
            $r = (float) ($obj['radius'] ?? 0);
            if ($r <= 0) {
                [$w, $h] = get_obj_wh($obj);
                $r = min($w, $h) / 2;
            }

            $n = 48;
            $flat = [];
            for ($i = 0; $i < $n; $i++) {
                $t = (2 * pi() * $i) / $n;
                $gp = mat_apply($m, $r * cos($t), $r * sin($t));
                $flat[] = $offX + $gp[0] * $scale;
                $flat[] = $offY + $gp[1] * $scale;
            }
            $pdf->Polygon($flat, $drawStyle, $lineStyle ?? [], $fillColor ?? []);
            if ($setAlpha) {
                $pdf->SetAlpha(1);
            }
            return;
        }

        if ($type === 'line') {
            $w = (float) ($obj['width'] ?? 0);
            $h = (float) ($obj['height'] ?? 0);
            $x1 = (float) ($obj['x1'] ?? 0);
            $x2 = (float) ($obj['x2'] ?? 0);
            $y1 = (float) ($obj['y1'] ?? 0);
            $y2 = (float) ($obj['y2'] ?? 0);
            if ($w <= 0) {
                $w = abs($x2 - $x1);
            }
            if ($h <= 0) {
                $h = abs($y2 - $y1);
            }

            $signX = ($x1 <= $x2) ? 1 : -1;
            $signY = ($y1 <= $y2) ? 1 : -1;

            $p1 = mat_apply($m, -($w / 2) * $signX, -($h / 2) * $signY);
            $p2 = mat_apply($m, ($w / 2) * $signX, ($h / 2) * $signY);

            [$x1Mm, $y1Mm] = px_to_mm($p1[0], $p1[1], $scale, $offX, $offY);
            [$x2Mm, $y2Mm] = px_to_mm($p2[0], $p2[1], $scale, $offX, $offY);

            $pdf->Line($x1Mm, $y1Mm, $x2Mm, $y2Mm, $lineStyle ?? []);
            if ($setAlpha) {
                $pdf->SetAlpha(1);
            }
            return;
        }
    }

    if ($setAlpha) {
        $pdf->SetAlpha(1);
    }
}

function montar_pdf_certificado(mysqli $conn, int $certCode): array
{
    $temCodigoValidacao = coluna_existe($conn, 'certificado', 'codigo_validacao');
    $temStatusCertificado = coluna_existe($conn, 'certificado', 'status_envio');

    $codigoValidacaoExpr = $temCodigoValidacao ? 'c.codigo_validacao' : 'NULL';
    $statusExpr = $temStatusCertificado ? 'COALESCE(c.status_envio, 0)' : '0';

    $sql = "
        SELECT
            c.codigo AS certificado_codigo,
            {$codigoValidacaoExpr} AS codigo_validacao,
            {$statusExpr} AS status_envio,
            c.data_emissao,
            c.texto_certificado,
            c.descricao AS certificado_descricao,
            c.carga_horaria AS certificado_carga_horaria,
            u.ID AS usuario_id,
            u.nome AS nome_usuario,
            u.email AS email_usuario,
            a.ID AS atividade_id,
            a.nome AS nome_atividade,
            a.palestrante AS nome_palestrante,
            a.informacoes_atividade,
            a.carga_horaria AS atividade_carga_horaria,
            e.codigo AS evento_id,
            e.nome AS nome_evento,
            e.data_inicio AS evento_data_inicio,
            e.data_fim AS evento_data_fim,
            t.imagem_preview,
            t.json AS template_json
        FROM certificado c
        JOIN usuario u ON u.ID = c.fk_Usuario_ID
        JOIN atividade a ON a.ID = c.fk_Atividade_ID
        JOIN evento e ON e.codigo = a.fk_Evento_codigo
        JOIN templatecertificado t ON t.fk_Atividade_ID = c.fk_Atividade_ID
        WHERE c.codigo = ?
        ORDER BY t.id DESC
        LIMIT 1
    ";

    $stmt = preparar($conn, $sql);
    $stmt->bind_param('i', $certCode);
    executar($stmt, 'consulta do certificado');
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        respond(404, [
            'success' => false,
            'message' => 'Certificado não encontrado ou atividade sem template salvo'
        ]);
    }

    if ($temCodigoValidacao && empty($row['codigo_validacao'])) {
        $novo = gerar_codigo_validacao_unico($conn);
        $u = preparar($conn, 'UPDATE certificado SET codigo_validacao = ? WHERE codigo = ?');
        $u->bind_param('si', $novo, $certCode);
        executar($u, 'atualização do código de validação');
        $u->close();
        $row['codigo_validacao'] = $novo;
    }

    if (empty($row['template_json'])) {
        throw new RuntimeException('template_json vazio no banco. Salve o template da atividade antes de baixar o PDF.');
    }

    $template = decode_json_safe($row['template_json']);
    if (!$template) {
        throw new RuntimeException('template_json inválido.');
    }

    $bg = $template['backgroundImage'] ?? null;
    $modeloFundo = encontrar_modelo_fundo_no_template($template);
    $backgroundSrc = '';

    $objects = is_array($template['objects'] ?? null) ? $template['objects'] : [];

    if (is_array($bg) && !empty($bg['src'])) {
        $backgroundSrc = (string) $bg['src'];
    } elseif (is_array($modeloFundo)) {
        $backgroundSrc = (string) ($modeloFundo['modeloSrc'] ?? ($modeloFundo['src'] ?? ''));
    } elseif (!empty($row['imagem_preview']) && count($objects) === 0) {
        // Fallback somente para template antigo sem objetos.
        // Se houver objetos, usar imagem_preview como fundo desenha o certificado já renderizado
        // e depois desenha os textos novamente, causando sobreposição no PDF/e-mail.
        $backgroundSrc = (string) $row['imagem_preview'];
    }

    if ($backgroundSrc === '') {
        throw new RuntimeException('Imagem base do certificado não encontrada no JSON do template. Abra o editor, clique em um modelo e salve novamente.');
    }

    [$imgPath, $fileType, $backgroundIsTmp] = imagem_source_para_arquivo($backgroundSrc);

    if ($fileType === 'JPG') {
        $fileType = 'JPEG';
    }

    if (strtoupper($fileType) === 'PNG' && pngHasTransparency($imgPath)) {
        respond(500, [
            'success' => false,
            'message' => 'A imagem base do certificado está em PNG com transparência. Converta a imagem para JPG ou PNG sem transparência.'
        ]);
    }

    $canvasW = 0.0;
    $canvasH = 0.0;

    if (isset($template['canvasWidth'], $template['canvasHeight'])) {
        $canvasW = (float) $template['canvasWidth'];
        $canvasH = (float) $template['canvasHeight'];
    } elseif (is_array($bg) && !empty($bg['width']) && !empty($bg['height'])) {
        $canvasW = (float) $bg['width'] * (float) ($bg['scaleX'] ?? 1);
        $canvasH = (float) $bg['height'] * (float) ($bg['scaleY'] ?? 1);
    } elseif (is_array($modeloFundo) && !empty($modeloFundo['width']) && !empty($modeloFundo['height'])) {
        $canvasW = (float) ($modeloFundo['width'] ?? 0) * (float) ($modeloFundo['scaleX'] ?? 1);
        $canvasH = (float) ($modeloFundo['height'] ?? 0) * (float) ($modeloFundo['scaleY'] ?? 1);
    }

    if ($canvasW <= 0 || $canvasH <= 0) {
        $size = @getimagesize($imgPath);
        if (!$size) {
            throw new RuntimeException('Não foi possível identificar as dimensões do certificado.');
        }
        $canvasW = (float) $size[0];
        $canvasH = (float) $size[1];
    }

    $codigoValidacao = (string) ($row['codigo_validacao'] ?: $row['certificado_codigo']);
    $codigoFormatado = formatar_codigo_validacao($codigoValidacao);
    $cargaHoraria = (int) ($row['certificado_carga_horaria'] ?? 0);
    if ($cargaHoraria <= 0) {
        $cargaHoraria = (int) ($row['atividade_carga_horaria'] ?? 0);
    }

    $dataEmissao = $row['data_emissao'] ?: date('Y-m-d');

    $map = [
        'NOME' => (string) ($row['nome_usuario'] ?? ''),
        'NOME_USUARIO' => (string) ($row['nome_usuario'] ?? ''),
        'NOME_PARTICIPANTE' => (string) ($row['nome_usuario'] ?? ''),
        'PARTICIPANTE' => (string) ($row['nome_usuario'] ?? ''),
        'ALUNO' => (string) ($row['nome_usuario'] ?? ''),
        'EMAIL' => (string) ($row['email_usuario'] ?? ''),
        'EMAIL_USUARIO' => (string) ($row['email_usuario'] ?? ''),
        'ATIVIDADE' => (string) ($row['nome_atividade'] ?? ''),
        'NOME_ATIVIDADE' => (string) ($row['nome_atividade'] ?? ''),
        'EVENTO' => (string) ($row['nome_evento'] ?? ''),
        'NOME_EVENTO' => (string) ($row['nome_evento'] ?? ''),
        'ASSINATURA' => (string) ($row['nome_palestrante'] ?? ''),
        'PALESTRANTE' => (string) ($row['nome_palestrante'] ?? ''),
        'NOME_PALESTRANTE' => (string) ($row['nome_palestrante'] ?? ''),
        'CODIGO' => $codigoFormatado,
        'CODIGO_CERTIFICADO' => $codigoFormatado,
        'CODIGO_VALIDACAO' => $codigoFormatado,
        'DATA' => formatar_data_br($dataEmissao),
        'DATA_EMISSAO' => formatar_data_br($dataEmissao),
        'DATA_INICIO' => formatar_data_br($row['evento_data_inicio'] ?? ''),
        'DATA_FIM' => formatar_data_br($row['evento_data_fim'] ?? ''),
        'CARGA_HORARIA' => $cargaHoraria > 0 ? (string) $cargaHoraria : '',
        'CARGA' => $cargaHoraria > 0 ? (string) $cargaHoraria : '',
    ];

    $pdfPath = sys_get_temp_dir() . '/certificado_' . preg_replace('/[^A-Za-z0-9]/', '', $codigoValidacao) . '_' . uniqid() . '.pdf';
    $filename = nome_arquivo_seguro((string) $row['nome_usuario']) . '_' . nome_arquivo_seguro((string) $row['nome_atividade']) . '.pdf';

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0, true);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->AddPage();

    if (method_exists($pdf, 'setCellPaddings')) {
        $pdf->setCellPaddings(0, 0, 0, 0);
    }
    if (method_exists($pdf, 'setCellMargins')) {
        $pdf->setCellMargins(0, 0, 0, 0);
    }

    $pdf->Image($imgPath, 0, 0, 297, 210, $fileType);

    $pageW = 297.0;
    $pageH = 210.0;
    $scale = min($pageW / $canvasW, $pageH / $canvasH);
    $offX = ($pageW - ($canvasW * $scale)) / 2.0;
    $offY = ($pageH - ($canvasH * $scale)) / 2.0;

    $identity = [1, 0, 0, 1, 0, 0];

    /*
     * Renderiza os objetos normais primeiro e as tags dinâmicas por último.
     * Isso impede que um texto comum fique por cima do nome do usuário,
     * e-mail, atividade, código, etc. depois da substituição.
     */
    $objectsOrdenados = ordenar_objetos_pdf_sem_sobrepor_tags($objects);

    foreach ($objectsOrdenados as $obj) {
        if (is_array($obj)) {
            render_fabric_obj($pdf, $obj, $identity, $scale, $offX, $offY, $map);
        }
    }

    $pdf->Output($pdfPath, 'F');

    if ($backgroundIsTmp && is_file($imgPath)) {
        @unlink($imgPath);
    }

    return [$pdfPath, $filename, $row];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, [
            'success' => false,
            'message' => 'Método não permitido'
        ]);
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        respond(500, [
            'success' => false,
            'message' => 'Conexão com o banco não encontrada'
        ]);
    }

    $conn->set_charset('utf8mb4');

    $raw = file_get_contents('php://input');
    $input = json_decode((string) $raw, true);

    if (!is_array($input)) {
        respond(400, [
            'success' => false,
            'message' => 'Body não veio como JSON válido',
            'raw' => $raw
        ]);
    }

    $certCode = trim((string) ($input['cod_certificado'] ?? $input['codigo'] ?? ''));
    if ($certCode === '' || !ctype_digit($certCode)) {
        respond(400, [
            'success' => false,
            'message' => 'cod_certificado não informado ou inválido'
        ]);
    }

    $modo = strtolower(trim((string) ($input['modo'] ?? $input['acao'] ?? 'email')));
    if (!in_array($modo, ['download', 'baixar', 'pdf', 'email', 'enviar'], true)) {
        respond(400, [
            'success' => false,
            'message' => 'Modo inválido. Use download ou email.'
        ]);
    }

    [$pdfPath, $filename, $row] = montar_pdf_certificado($conn, (int) $certCode);

    $statusAtual = (int) ($row['status_envio'] ?? 0);

    // Download não marca o certificado como enviado.
    // O status_envio continua representando somente envio por e-mail.
    if (in_array($modo, ['download', 'baixar', 'pdf'], true)) {
        enviar_pdf_para_download($pdfPath, $filename, $statusAtual);
    }

    // Mantém compatibilidade com o envio por e-mail já existente.
    // Se houver variáveis de ambiente, elas têm prioridade.
    $smtpUser = getenv('SICAD_SMTP_USER') ?: 'sicad.certificados@gmail.com';
    $smtpPass = getenv('SICAD_SMTP_PASS') ?: 'dtrt frya etbb ohhy';

    if ($smtpUser === '' || $smtpPass === '') {
        @unlink($pdfPath);
        respond(500, [
            'success' => false,
            'message' => 'SMTP não configurado. Defina SICAD_SMTP_USER e SICAD_SMTP_PASS no servidor ou use modo download.'
        ]);
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = getenv('SICAD_SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) (getenv('SICAD_SMTP_PORT') ?: 587);
    $mail->CharSet = 'UTF-8';

    $mail->setFrom($smtpUser, 'SICAD - Certificados');
    $mail->addAddress((string) $row['email_usuario'], (string) $row['nome_usuario']);
    $mail->Subject = 'Seu certificado está disponível!';
    $mail->Body = "Olá {$row['nome_usuario']},\n\nSegue em anexo seu certificado da atividade \"{$row['nome_atividade']}\".\n\nAtenciosamente,\nEquipe SICAD";
    $mail->addAttachment($pdfPath, $filename);
    $mail->send();

    $statusAtual = atualizar_status_certificado($conn, (int) $certCode, (int) $row['atividade_id']);

    if (is_file($pdfPath)) {
        @unlink($pdfPath);
    }

    respond(200, [
        'success' => true,
        'message' => 'Certificado enviado com sucesso',
        'status' => $statusAtual
    ]);
} catch (Throwable $e) {
    error_log('enviar_certificado.php: ' . $e->getMessage());

    respond(500, [
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
