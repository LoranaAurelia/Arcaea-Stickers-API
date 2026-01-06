<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

if (!extension_loaded('gd')) {
    send_json(['error' => 'gd_extension_required'], 500);
}

$siteRoot = realpath(__DIR__ . '/..');
if ($siteRoot === false) send_json(['error' => 'site_root_not_found'], 500);

$charactersPath = realpath(__DIR__ . '/characters.json');
if ($charactersPath === false) send_json(['error' => 'characters_json_missing'], 500);
$characters = json_decode(file_get_contents($charactersPath), true);
if (!is_array($characters)) send_json(['error' => 'characters_json_invalid'], 500);

$byChar = [];
$byId = [];
foreach ($characters as $c) {
    if (!is_array($c)) continue;

    if (isset($c['id'])) {
        $byId[(string)$c['id']] = $c;
    }
    if (isset($c['character'])) {
        $k = (string)$c['character'];
        if (!isset($byChar[$k])) $byChar[$k] = $c; // 保留第一个作为默认
    }
}

$ch = trim((string)($_GET['ch'] ?? $_GET['character'] ?? ''));
$id = trim((string)($_GET['id'] ?? ''));

// 先按 id 精确匹配，再退回到 character
$cfg = null;
if ($id !== '' && isset($byId[$id])) {
    $cfg = $byId[$id];
} elseif ($ch !== '' && isset($byId[$ch])) {   // 兼容前端把 id 塞到 ch
    $cfg = $byId[$ch];
} elseif ($ch !== '' && isset($byChar[$ch])) { // 兼容旧链接 ch=maya / ch=tairitsu
    $cfg = $byChar[$ch];
}
if ($cfg === null) {
    send_json(['error' => 'unknown_character', 'ch' => $ch], 400);
}

$text = (string)($_GET['text'] ?? $_GET['t'] ?? ($cfg['defaultText']['text'] ?? ''));
$text = str_replace("\r\n", "\n", $text);
$text = str_replace("\r", "\n", $text);
$text = trim($text);
if (mb_strlen($text, 'UTF-8') > 120) {
    $text = mb_substr($text, 0, 120, 'UTF-8');
}

$x = safe_int('x', (int)($cfg['defaultText']['x'] ?? 148), 0, 296);
$y = safe_int('y', (int)($cfg['defaultText']['y'] ?? 58), -256, 512);
$s = safe_int('s', (int)($cfg['defaultText']['s'] ?? 42), 10, 100);
$sp = safe_int('sp', 50, 0, 100);
$r = safe_float('r', (float)($cfg['defaultText']['r'] ?? 0.0), -10.0, 10.0);
$curve = safe_int('curve', 0, 0, 1) === 1;
$scale = safe_int('scale', 1, 1, 4);

$angle = $r / 10.0; // radians

// --- Geometry mapping ---
// UI coordinates are based on the original 296x256 canvas.
// We render on a slightly larger canvas (same aspect ratio) so long text doesn't get clipped.
$BASE_W = 296; $BASE_H = 256;
// Keep aspect ratio 296:256 = 37:32. Use k=11 (was 8) => 407x352.
$CANVAS_W = 346; $CANVAS_H = 299;
$PAD_X = intdiv(($CANVAS_W - $BASE_W), 2); // 55
$PAD_Y = intdiv(($CANVAS_H - $BASE_H), 2) + 1; // 48

$angle = $r / 10.0; // radians

// scale coordinates (UI is 296x256), then add padding offsets (in UI units)
$x = (int)round(($x + $PAD_X) * $scale);
$y = (int)round(($y + $PAD_Y) * $scale);

// Font size:
// - UI `s` is CSS px (Canvas). GD/FreeType expects "points" at 72dpi, while CSS px is 96dpi.
//   So: pt ~= px * 72/96 = px * 0.75
$sCssPx = $s;
$sPxScaled = $sCssPx * $scale;
$sTtf = $sPxScaled * 0.75;


$fillHex = (string)($cfg['fillColor'] ?? '#FFFFFF');
$strokeHex = (string)($cfg['strokeColor'] ?? '#000000');

$imgName = basename((string)($cfg['img'] ?? ''));
if ($imgName === '' || !preg_match('/^[A-Za-z0-9._-]+\.(png)$/', $imgName)) {
    send_json(['error' => 'invalid_img'], 500);
}

$imgRoot = realpath($siteRoot . DIRECTORY_SEPARATOR . 'img');
if ($imgRoot === false) send_json(['error' => 'img_dir_missing'], 500);
$imgPath = realpath($imgRoot . DIRECTORY_SEPARATOR . $imgName);
if ($imgPath === false || !str_starts_with($imgPath, $imgRoot . DIRECTORY_SEPARATOR)) {
    send_json(['error' => 'img_not_found'], 404);
}

$fontChoice = strtolower(trim((string)($_GET['font'] ?? 'auto')));
$fontYuruka = realpath(__DIR__ . '/fonts/YurukaStd.ttf');
$fontFang = realpath(__DIR__ . '/fonts/ShangShouFangTangTi.ttf');
if ($fontYuruka === false || $fontFang === false) send_json(['error' => 'font_missing'], 500);

if ($fontChoice === 'yuruka') $fontFile = $fontYuruka;
else if ($fontChoice === 'fang' || $fontChoice === 'ssfangtangti') $fontFile = $fontFang;
else $fontFile = contains_cjk($text) ? $fontFang : $fontYuruka;

$ver = 'dropin-v6';
$key = sha1(json_encode([
  'ver' => $ver,
  'id'  => (string)($cfg['id'] ?? ''),
  'img' => (string)($cfg['img'] ?? ''),
  'text' => $text,
  'x' => $x, 'y' => $y,
  's' => $s, 'sp' => $sp,
  'r' => $r,
  'curve' => $curve ? 1 : 0,
  'scale' => $scale,
  'font' => basename($fontFile),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$cacheDir = arcst_storage_dir($siteRoot) . DIRECTORY_SEPARATOR . 'render_cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
$cachePath = $cacheDir . DIRECTORY_SEPARATOR . $key . '.png';
$etag = '"' . $key . '"';

if (is_file($cachePath)) {
    $ifNone = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    if (trim((string)$ifNone) === $etag && !isset($_GET['download'])) {
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=31536000, immutable');
        http_response_code(304);
        exit;
    }
    header('Content-Type: image/png');
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=31536000, immutable');
    if (isset($_GET['download'])) {
        $fn = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)($cfg['name'] ?? 'sticker'));
        header('Content-Disposition: attachment; filename="' . $fn . '.png"');
    }
    readfile($cachePath);
    exit;
}

$W = $CANVAS_W * $scale; $H = $CANVAS_H * $scale;
$im = imagecreatetruecolor($W, $H);
imagealphablending($im, false);
imagesavealpha($im, true);
$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefill($im, 0, 0, $transparent);
imagealphablending($im, true);
imageantialias($im, true);

$src = @imagecreatefrompng($imgPath);
if ($src === false) {
    imagedestroy($im);
    send_json(['error' => 'failed_to_load_base_image'], 500);
}
$sw = imagesx($src);
$sh = imagesy($src);

$baseW = $BASE_W * $scale;
$baseH = $BASE_H * $scale;
$padXpx = $PAD_X * $scale;
$padYpx = $PAD_Y * $scale;

// Fit character image into the original 296x256 area, centered inside the larger canvas.
$ratio = min($baseW / max(1, $sw), $baseH / max(1, $sh));
$dw = (int)round($sw * $ratio);
$dh = (int)round($sh * $ratio);
$dx = (int)round($padXpx + ($baseW - $dw) / 2);
$dy = (int)round($padYpx + ($baseH - $dh) / 2);

imagecopyresampled($im, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
imagedestroy($src);

$fill = alloc_color($im, $fillHex, 0);
$stroke = alloc_color($im, $strokeHex, 0);
$white = alloc_color($im, '#FFFFFF', 0);

$lines = ($text === '') ? [] : preg_split("/\n/u", $text);
$lineStep = ($sp / 50.0) * $sPxScaled;
$outerR = (int)max(1, round((15.0/2.0) * $scale));
$innerR = (int)max(1, round((5.0/2.0) * $scale));

$cos = cos($angle);
$sin = sin($angle);

if (!$curve) {
    for ($li = 0; $li < count($lines); $li++) {
        $line = (string)$lines[$li];
        $k = $li * $lineStep;
        $tx = $x + (0.0 * $cos - $k * $sin);
        $ty = $y + (0.0 * $sin + $k * $cos);
        draw_text_baseline_center($im, $fontFile, $sTtf, $angle, $tx, $ty, $fill, $stroke, $innerR, $white, $outerR, $line);
    }
} else {
    $delta = M_PI / (7.0 * 2.2);
    $radius = $sPxScaled * 3.5;
    for ($li = 0; $li < count($lines); $li++) {
        $line = (string)$lines[$li];
        if ($line === '') continue;
        $k = $li * $lineStep;
        $lox = $x + (0.0 * $cos - $k * $sin);
        $loy = $y + (0.0 * $sin + $k * $cos);
        $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) continue;
        $theta = $angle;
        foreach ($chars as $chv) {
            $theta += $delta;
            $ccos = cos($theta);
            $csin = sin($theta);
            $px = $lox + (0.0 * $ccos - (-$radius) * $csin);
            $py = $loy + (0.0 * $csin + (-$radius) * $ccos);
            draw_text_baseline_center($im, $fontFile, $sTtf, $theta, $px, $py, $fill, $stroke, $innerR, $white, $outerR, (string)$chv);
        }
    }
}

$tmp = $cachePath . '.tmp';
imagepng($im, $tmp);
imagedestroy($im);
@rename($tmp, $cachePath);

header('Content-Type: image/png');
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=31536000, immutable');
if (isset($_GET['download'])) {
    $fn = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)($cfg['name'] ?? 'sticker'));
    header('Content-Disposition: attachment; filename="' . $fn . '.png"');
}
readfile($cachePath);
