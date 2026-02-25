<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(E_ALL);

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);




if (!is_array($input)) {
  echo json_encode(["success" => false, "message" => "Body não veio como JSON válido", "raw" => $raw]);
  exit;
}

$certCode = trim((string) ($input['cod_certificado'] ?? ''));
if ($certCode === '') {
  echo json_encode(["success" => false, "message" => "cod_certificado não informado"]);
  exit;
}


require('db.php');
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;



try {


  // Query com seus nomes reais
  $sql = "
  SELECT
  c.codigo_validacao AS cod_certificado,
  u.nome AS nome_usuario,
  u.email AS email_usuario,
  a.nome AS nome_atividade,
  a.palestrante AS nome_palestrante,
  t.imagem_preview,
  t.json AS template_json
FROM certificado c
JOIN usuario u ON u.ID = c.fk_Usuario_ID
JOIN atividade a ON a.ID = c.fk_Atividade_ID
JOIN templatecertificado t ON t.fk_Atividade_ID = c.fk_Atividade_ID
WHERE c.codigo = ?
LIMIT 1
";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $certCode);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if (!$row) {
    echo json_encode(["success" => false, "message" => "Certificado não encontrado"]);
    exit;
  }

  if (empty($row['cod_certificado'])) {
    $novo = strtoupper(bin2hex(random_bytes(12))); // 24 chars (HEX)
    $u = $conn->prepare("UPDATE certificado SET codigo_validacao = ? WHERE codigo = ?");
    $u->bind_param("si", $novo, $certCode);
    $u->execute();
    $u->close();

    $row['cod_certificado'] = $novo; // mantém na memória para PDF e validação
  }
  // depois do $row carregado
  if (empty($row['imagem_preview'])) {
    throw new Exception("imagem_preview vazio no banco");
  }
  if (empty($row['template_json'])) {
    throw new Exception("template_json vazio no banco (não tem como desenhar textos)");
  }

  function resolve_local_image_path(string $p): string
  {
    $p = str_replace('\\', '/', $p);

    if (preg_match('#^https?://#i', $p)) {
      $p = parse_url($p, PHP_URL_PATH) ?: '';
    }

    $p = preg_replace('#^(\.\./)+#', '', $p);
    $p = '/' . ltrim($p, '/');

    $docroot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');

    if (substr($docroot, -6) === '/SICAD' && strpos($p, '/SICAD/') === 0) {
      $p = substr($p, 6);
    }

    $full = $docroot . $p;
    $real = realpath($full);

    if ($real === false || !is_file($real)) {
      throw new Exception("Imagem base do certificado não encontrada. docroot={$docroot} webPath={$p} fullPath={$full}");
    }

    return $real;
  }

  function decode_json_safe($raw)
  {
    if (!is_string($raw))
      return null;
    $j = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE)
      return $j;
    $j = json_decode(stripslashes($raw), true);
    if (json_last_error() === JSON_ERROR_NONE)
      return $j;
    return null;
  }
  function hex_to_rgb($hex)
  {
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3)
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    if (strlen($hex) !== 6)
      return [0, 0, 0];
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
  }
  function align_fabric_to_tcpdf($a)
  {
    $a = strtolower((string) $a);
    if ($a === 'center')
      return 'C';
    if ($a === 'right')
      return 'R';
    return 'L';
  }
  function replace_placeholders($text, array $map)
  {
    return preg_replace_callback('/\{\{\s*([A-Z0-9_]+)\s*\}\}/i', function ($m) use ($map) {
      $k = strtoupper($m[1]);
      return array_key_exists($k, $map) ? (string) $map[$k] : '';
    }, (string) $text);
  }

  $imgPath = resolve_local_image_path($row['imagem_preview']);
  $template = decode_json_safe($row['template_json']);
  if (!$template)
    throw new Exception("template_json inválido");

  $bg = $template['backgroundImage'] ?? null;
  if (!is_array($bg) || empty($bg['width']) || empty($bg['height'])) {
    throw new Exception("Fabric JSON sem backgroundImage.width/height");
  }

  $canvasW = (float) $bg['width'] * ((float) ($bg['scaleX'] ?? 1));
  $canvasH = (float) $bg['height'] * ((float) ($bg['scaleY'] ?? 1));

  $codigoFormatado = implode('-', str_split(strtoupper($row['cod_certificado']), 4));

  $map = [
    "NOME" => $row['nome_usuario'],
    "ATIVIDADE" => $row['nome_atividade'],
    "ASSINATURA" => $row['nome_palestrante'],
    "PALESTRANTE" => $row['nome_palestrante'],
    "CODIGO" => $codigoFormatado, 
  ];


  // ===== PDF =====
  $pdfPath = sys_get_temp_dir() . "/certificado_" . $row['cod_certificado'] . ".pdf";

  $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(false);
  $pdf->SetMargins(0, 0, 0, true);
  $pdf->SetAutoPageBreak(false, 0);
  $pdf->AddPage();

  if (method_exists($pdf, 'setCellPaddings'))
    $pdf->setCellPaddings(0, 0, 0, 0);
  if (method_exists($pdf, 'setCellMargins'))
    $pdf->setCellMargins(0, 0, 0, 0);

  // fundo
  $pdf->Image($imgPath, 0, 0, 297, 210, 'PNG');

  // escala
  $pageW = 297.0;
  $pageH = 210.0;
  $scale = min($pageW / $canvasW, $pageH / $canvasH);
  $offX = ($pageW - ($canvasW * $scale)) / 2.0;
  $offY = ($pageH - ($canvasH * $scale)) / 2.0;

  // textos
  $objects = is_array($template['objects'] ?? null) ? $template['objects'] : [];
  $IDENT = [1, 0, 0, 1, 0, 0]; // matriz identidade (a,b,c,d,e,f)

  function parse_color($c): array
  {
    $c = trim((string) $c);
    if ($c === '' || strtolower($c) === 'transparent' || strtolower($c) === 'none') {
      return [0, 0, 0, 0];
    }
    if ($c[0] === '#') {
      $hex = ltrim($c, '#');
      if (strlen($hex) === 3)
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
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

  // Matrizes 2D (a,b,c,d,e,f)
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
    return [(float) ($obj['width'] ?? 0), (float) ($obj['height'] ?? 0)];
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

    // getScaledWidth/Height usa módulo
    $scaledW = abs($w * $sx0);
    $scaledH = abs($h * $sy0);

    $cx = $left;
    if ($originX === 'left')
      $cx = $left + $scaledW / 2;
    elseif ($originX === 'right')
      $cx = $left - $scaledW / 2;

    $cy = $top;
    if ($originY === 'top')
      $cy = $top + $scaledH / 2;
    elseif ($originY === 'bottom')
      $cy = $top - $scaledH / 2;

    // T(cx,cy) * R(angle) * S(sx,sy)
    return [$cos * $sx, $sin * $sx, -$sin * $sy, $cos * $sy, $cx, $cy];
  }

  function px_to_mm(float $xPx, float $yPx, float $scale, float $offX, float $offY): array
  {
    return [$offX + $xPx * $scale, $offY + $yPx * $scale];
  }

  function style_from_obj(array $obj, float $scale): array
  {
    // stroke
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
        // TCPDF normalmente aceita string "2,2"
        $lineStyle['dash'] = implode(',', $dashMm);
      }
    }

    // fill
    [$fr, $fg, $fb, $fa] = parse_color($obj['fill'] ?? '');
    $fa *= $opacity;

    $fillColor = null;
    if ($fa > 0.001 && ($obj['fill'] ?? '') !== '') {
      $fillColor = [$fr, $fg, $fb];
    }

    $alpha = max($sa, $fa);
    return [$lineStyle, $fillColor, $alpha];
  }

  function fabric_src_to_file(string $src): array {
  $src = trim($src);
  if ($src === '') {
    throw new Exception("Imagem do Fabric sem src");
  }

  // data:image/png;base64,...
  if (preg_match('#^data:image/([^;]+);base64,#i', $src, $m)) {
    $mime = strtolower($m[1]); // png, jpeg, webp...
    $ext = $mime;
    if ($ext === 'jpeg') $ext = 'jpg';
    if ($ext === 'svg+xml') $ext = 'svg';

    $commaPos = strpos($src, ',');
    if ($commaPos === false) throw new Exception("DataURL inválido (sem vírgula)");
    $b64 = substr($src, $commaPos + 1);

    $bin = base64_decode($b64, true);
    if ($bin === false) {
      throw new Exception("Base64 da imagem inválido");
    }

    $tmp = sys_get_temp_dir() . "/fabric_img_" . uniqid() . "." . $ext;
    if (@file_put_contents($tmp, $bin) === false) {
      throw new Exception("Não consegui gravar imagem temporária em: $tmp");
    }

    return [$tmp, strtoupper($ext), true]; // (arquivo, tipo TCPDF, é temporário?)
  }

  // http(s)://...
  if (preg_match('#^https?://#i', $src)) {
    $bin = @file_get_contents($src);
    if ($bin === false) {
      throw new Exception("Não consegui baixar imagem: $src (allow_url_fopen desativado?)");
    }
    $tmp = sys_get_temp_dir() . "/fabric_img_" . uniqid() . ".png";
    file_put_contents($tmp, $bin);
    return [$tmp, 'PNG', true];
  }

  // caminho local do servidor (caso você um dia salve imagem como arquivo)
  $local = resolve_local_image_path($src);
  $ext = strtolower(pathinfo($local, PATHINFO_EXTENSION) ?: 'png');
  if ($ext === 'jpeg') $ext = 'jpg';
  return [$local, strtoupper($ext), false];
}

  function render_fabric_obj(TCPDF $pdf, array $obj, array $parentM, float $scale, float $offX, float $offY, array $map): void
  {
    $type = $obj['type'] ?? '';

    // GROUP (setas): renderiza filhos com matriz composta
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
    if ($setAlpha)
      $pdf->SetAlpha(max(0, min(1, $alpha)));

    // ===== TEXTOS (com e sem {{}}) =====
    // ===== TEXTOS (com e sem {{}}) =====
    if (in_array($type, ['i-text', 'text', 'textbox'], true)) {
      $raw = (string) ($obj['text'] ?? '');
      if ($raw === '') {
        if ($setAlpha)
          $pdf->SetAlpha(1);
        return;
      }

      $text = (strpos($raw, '{{') !== false) ? replace_placeholders($raw, $map) : $raw;

      // posição do canto sup-esq do bounding box (sem rotação)
      [$w, $h] = get_obj_wh($obj);
      $sxAbs = abs((float) ($obj['scaleX'] ?? 1));
      $syAbs = abs((float) ($obj['scaleY'] ?? 1));
      $scaledW = $w * $sxAbs;
      $scaledH = $h * $syAbs;

      $originX = strtolower((string) ($obj['originX'] ?? 'left'));
      $originY = strtolower((string) ($obj['originY'] ?? 'top'));
      $left = (float) ($obj['left'] ?? 0);
      $top = (float) ($obj['top'] ?? 0);

      $xPx = $left;
      if ($originX === 'center')
        $xPx = $left - $scaledW / 2;
      elseif ($originX === 'right')
        $xPx = $left - $scaledW;

      $yPx = $top;
      if ($originY === 'center')
        $yPx = $top - $scaledH / 2;
      elseif ($originY === 'bottom')
        $yPx = $top - $scaledH;

      [$xMm, $yMm] = px_to_mm($xPx, $yPx, $scale, $offX, $offY);

      // ===== Fonte =====
      $fontSizePx = (float) ($obj['fontSize'] ?? 16) * $syAbs; // altura segue scaleY
      $fontPt = max(6, ($fontSizePx * $scale) * 72.0 / 25.4);

      $style = '';
      if (strtolower((string) ($obj['fontWeight'] ?? '')) === 'bold')
        $style .= 'B';
      if (strtolower((string) ($obj['fontStyle'] ?? '')) === 'italic')
        $style .= 'I';
      if (!empty($obj['underline']))
        $style .= 'U';

      [$r, $g, $b] = array_slice(parse_color($obj['fill'] ?? '#000'), 0, 3);
      $pdf->SetTextColor($r, $g, $b);

      $ff = strtolower((string) ($obj['fontFamily'] ?? ''));
      $tcpdfFont = 'dejavusans';
      if (strpos($ff, 'times') !== false)
        $tcpdfFont = 'times';
      elseif (strpos($ff, 'courier') !== false)
        $tcpdfFont = 'courier';
      elseif (strpos($ff, 'arial') !== false || strpos($ff, 'verdana') !== false || strpos($ff, 'georgia') !== false)
        $tcpdfFont = 'helvetica';

      $pdf->SetFont($tcpdfFont, $style, $fontPt);

      // ===== AQUI ESTÁ O PULO DO GATO =====
      // 1) Simula o scaleX diferente do scaleY (texto "achatado/esticado" no Fabric)
      if (method_exists($pdf, 'SetFontStretching')) {
        $stretch = ($syAbs > 0) ? (100.0 * ($sxAbs / $syAbs)) : 100.0;
        // evita valores absurdos
        $pdf->SetFontStretching(max(10, min(300, $stretch)));
      }

      // 2) lineHeight (para o espaçamento entre linhas ficar mais parecido com o Fabric)
      $lineHeight = (float) ($obj['lineHeight'] ?? 1.16);
      $hMm = max(0.0, ($fontSizePx * $lineHeight) * $scale);

      $align = align_fabric_to_tcpdf($obj['textAlign'] ?? 'left');

      // 3) Evita wrap inesperado: garante que a largura do "box" no PDF
      //    nunca fique menor que a largura real das linhas no PDF.
      $wMmFabric = ($scaledW > 0) ? ($scaledW * $scale) : 0.0;
      $cellW = $wMmFabric;

      // Para i-text/text normalmente NÃO queremos que o TCPDF rewrappe.
      // Para textbox, ele é feito pra quebrar mesmo, então mantemos a largura.
      if ($type !== 'textbox') {
        $lines = preg_split("/\r\n|\r|\n/", $text);
        $maxLineW = 0.0;
        foreach ($lines as $ln) {
          $maxLineW = max($maxLineW, $pdf->GetStringWidth($ln));
        }
        $cellW = max($cellW, $maxLineW + 0.5); // +0.5mm de folga

        // mantém o "ancoramento" do texto (não desloca visualmente centro/direita)
        $delta = $cellW - $wMmFabric;
        if ($delta > 0.001) {
          if ($align === 'C')
            $xMm -= $delta / 2.0;
          elseif ($align === 'R')
            $xMm -= $delta;
        }
      }

      $pdf->SetXY($xMm, $yMm);
      $pdf->MultiCell(
        $cellW > 0 ? $cellW : 0,
        $hMm,
        $text,
        0,
        $align,
        false,
        1,
        '',   // x
        '',   // y
        true, // reseth
        0,    // stretch
        false,// ishtml
        false // autopadding  <<< IMPORTANTE
      );


      // reset (muito importante pra não afetar o próximo texto!)
      if (method_exists($pdf, 'SetFontStretching'))
        $pdf->SetFontStretching(100);

      if ($setAlpha)
        $pdf->SetAlpha(1);
      return;
    }

        // ===== IMAGENS (fabric.Image) =====
    if ($type === 'image') {
      $src = (string)($obj['src'] ?? '');
      if ($src === '') {
        if ($setAlpha) $pdf->SetAlpha(1);
        return;
      }

      // Imagem usa "opacity", não fill/stroke
      $opacity = (float)($obj['opacity'] ?? 1);
      if ($setAlpha) $pdf->SetAlpha(max(0, min(1, $opacity)));

      // tamanho base do objeto no Fabric
      [$w, $h] = get_obj_wh($obj);

      $sxAbs = abs((float)($obj['scaleX'] ?? 1));
      $syAbs = abs((float)($obj['scaleY'] ?? 1));
      $scaledWpx = $w * $sxAbs;
      $scaledHpx = $h * $syAbs;

      // posição do canto superior esquerdo (sem rotação), respeitando originX/originY
      $originX = strtolower((string)($obj['originX'] ?? 'left'));
      $originY = strtolower((string)($obj['originY'] ?? 'top'));
      $left = (float)($obj['left'] ?? 0);
      $top  = (float)($obj['top']  ?? 0);

      $xPx = $left;
      if ($originX === 'center') $xPx = $left - ($scaledWpx / 2);
      elseif ($originX === 'right') $xPx = $left - $scaledWpx;

      $yPx = $top;
      if ($originY === 'center') $yPx = $top - ($scaledHpx / 2);
      elseif ($originY === 'bottom') $yPx = $top - $scaledHpx;

      // converte pra mm
      [$xMm, $yMm] = px_to_mm($xPx, $yPx, $scale, $offX, $offY);
      $wMm = max(0.1, $scaledWpx * $scale);
      $hMm = max(0.1, $scaledHpx * $scale);

      // centro (pra rotacionar/flip)
      $cxMm = $xMm + ($wMm / 2);
      $cyMm = $yMm + ($hMm / 2);

      // resolve src -> arquivo
      [$imgFile, $imgType, $isTmp] = fabric_src_to_file($src);

      $angle = (float)($obj['angle'] ?? 0);
      $flipX = !empty($obj['flipX']);
      $flipY = !empty($obj['flipY']);

      // Transformações: StartTransform + (flip) + Rotate + StopTransform
      // TCPDF tem StartTransform/Rotate/MirrorH/MirrorV/StopTransform. :contentReference[oaicite:2]{index=2}
      if (method_exists($pdf, 'StartTransform')) {
        $pdf->StartTransform();

        // Flip primeiro, depois Rotate (mesma ordem lógica: Scale/Flip -> Rotate)
        if ($flipX && method_exists($pdf, 'MirrorH')) $pdf->MirrorH($cxMm);
        if ($flipY && method_exists($pdf, 'MirrorV')) $pdf->MirrorV($cyMm);

        if (abs($angle) > 0.001 && method_exists($pdf, 'Rotate')) {
          $pdf->Rotate($angle, $cxMm, $cyMm);
        }
      }

      $pdf->Image($imgFile, $xMm, $yMm, $wMm, $hMm, $imgType);

      if (method_exists($pdf, 'StopTransform')) {
        $pdf->StopTransform();
      }

      if ($isTmp && is_file($imgFile)) @unlink($imgFile);

      if ($setAlpha) $pdf->SetAlpha(1);
      return;
    }
    // ===== FORMAS =====
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
        if ($setAlpha)
          $pdf->SetAlpha(1);
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
        if ($setAlpha)
          $pdf->SetAlpha(1);
        return;
      }

      if ($type === 'circle') {
        $r = (float) ($obj['radius'] ?? 0);
        if ($r <= 0) {
          [$w, $h] = get_obj_wh($obj);
          $r = min($w, $h) / 2;
        }
        // aproxima por polígono (funciona com rotação/escala/grupo)
        $n = 48;
        $flat = [];
        for ($i = 0; $i < $n; $i++) {
          $t = (2 * pi() * $i) / $n;
          $gp = mat_apply($m, $r * cos($t), $r * sin($t));
          $flat[] = $offX + $gp[0] * $scale;
          $flat[] = $offY + $gp[1] * $scale;
        }
        $pdf->Polygon($flat, $drawStyle, $lineStyle ?? [], $fillColor ?? []);
        if ($setAlpha)
          $pdf->SetAlpha(1);
        return;
      }

      if ($type === 'line') {
        $w = (float) ($obj['width'] ?? 0);
        $h = (float) ($obj['height'] ?? 0);
        $x1 = (float) ($obj['x1'] ?? 0);
        $x2 = (float) ($obj['x2'] ?? 0);
        $y1 = (float) ($obj['y1'] ?? 0);
        $y2 = (float) ($obj['y2'] ?? 0);
        if ($w <= 0)
          $w = abs($x2 - $x1);
        if ($h <= 0)
          $h = abs($y2 - $y1);

        $signX = ($x1 <= $x2) ? 1 : -1;
        $signY = ($y1 <= $y2) ? 1 : -1;

        $p1 = mat_apply($m, -($w / 2) * $signX, -($h / 2) * $signY);
        $p2 = mat_apply($m, ($w / 2) * $signX, ($h / 2) * $signY);

        [$x1Mm, $y1Mm] = px_to_mm($p1[0], $p1[1], $scale, $offX, $offY);
        [$x2Mm, $y2Mm] = px_to_mm($p2[0], $p2[1], $scale, $offX, $offY);

        $pdf->Line($x1Mm, $y1Mm, $x2Mm, $y2Mm, $lineStyle ?? []);
        if ($setAlpha)
          $pdf->SetAlpha(1);
        return;
      }
    }

    if ($setAlpha)
      $pdf->SetAlpha(1);
  }


  foreach ($objects as $obj) {
    if (is_array($obj)) {
      render_fabric_obj($pdf, $obj, $IDENT, $scale, $offX, $offY, $map);
    }
  }

  foreach ($objects as $o) {
    if (($o['type'] ?? '') === 'i-text') {
      error_log("TXT='{$o['text']}' scaleX=" . ($o['scaleX'] ?? 1) . " scaleY=" . ($o['scaleY'] ?? 1) . " width=" . ($o['width'] ?? 0) . " font=" . ($o['fontFamily'] ?? ''));
    }
  }


  // só agora salva
  $pdf->Output($pdfPath, 'F');



  // 5) Envia e-mail (troque credenciais e NÃO deixe senha hardcoded)
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = 'smtp.gmail.com';
  $mail->SMTPAuth = true;
  $mail->Username = 'sicad@atomicmail.io';
  $mail->Password = 'sicad@2025'; // senha de app
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = 587;
  $mail->CharSet = "UTF-8";

  $mail->setFrom('sicad@atomicmail.io', 'SICAD - Certificados');
  $mail->addAddress($row['email_usuario'], $row['nome_usuario']);
  $mail->Subject = 'Seu certificado está disponível!';
  $mail->Body = "Olá {$row['nome_usuario']},\n\nSegue em anexo seu certificado da atividade \"{$row['nome_atividade']}\".\n\nAtenciosamente,\nEquipe SICAD";
  $mail->addAttachment($pdfPath, "certificado.pdf");
  $mail->send();

  // 6) Atualiza status_envio
  $upd = $conn->prepare("UPDATE certificado SET status_envio = 1 WHERE codigo = ?");
  $upd->bind_param("i", $certCode);

  if (!$upd->execute()) {
    throw new Exception("Falha ao atualizar status_envio: " . $upd->error);
  }

  // (opcional) confirma no banco qual ficou o status
  $chk = $conn->prepare("SELECT IFNULL(status_envio,0) AS status FROM certificado WHERE codigo = ? LIMIT 1");
  $chk->bind_param("s", $certCode);
  $chk->execute();
  $stRow = $chk->get_result()->fetch_assoc();
  $statusAtual = (int) ($stRow['status'] ?? 0);


  if (file_exists($pdfPath))
    unlink($pdfPath);


  echo json_encode([
    "success" => true,
    "message" => "Certificado enviado com sucesso",
    "status" => $statusAtual
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    "success" => false,
    "message" => $e->getMessage()
  ]);
}

