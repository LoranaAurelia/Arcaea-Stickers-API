<?php
declare(strict_types=1);

function send_json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function safe_int(string $key, int $default, int $min, int $max): int {
    if (!isset($_GET[$key])) return $default;
    $v = filter_var($_GET[$key], FILTER_VALIDATE_FLOAT);
    if ($v === false) return $default;
    $i = (int)round((float)$v);
    if ($i < $min) $i = $min;
    if ($i > $max) $i = $max;
    return $i;
}

function safe_float(string $key, float $default, float $min, float $max): float {
    if (!isset($_GET[$key])) return $default;
    $v = filter_var($_GET[$key], FILTER_VALIDATE_FLOAT);
    if ($v === false) return $default;
    $f = (float)$v;
    if ($f < $min) $f = $min;
    if ($f > $max) $f = $max;
    return $f;
}

function hex_to_rgb(string $hex): array {
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return [255,255,255];
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function alloc_color($im, string $hex, int $alpha = 0): int {
    [$r,$g,$b] = hex_to_rgb($hex);
    $a = max(0, min(127, $alpha));
    return imagecolorallocatealpha($im, $r, $g, $b, $a);
}

function contains_cjk(string $s): bool {
    return (bool)preg_match('/[\x{3040}-\x{30FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}\x{AC00}-\x{D7AF}]/u', $s);
}

function circle_offsets(int $r): array {
    $r = max(0, $r);
    static $mem = [];
    if (isset($mem[$r])) return $mem[$r];

    $key = 'arcst_offsets_' . $r;
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $key . '.php';
    if (is_file($tmp)) {
        $data = include $tmp;
        if (is_array($data)) {
            $mem[$r] = $data;
            return $data;
        }
    }

    $pts = [];
    for ($dy = -$r; $dy <= $r; $dy++) {
        for ($dx = -$r; $dx <= $r; $dx++) {
            if ($dx*$dx + $dy*$dy <= $r*$r) $pts[] = [$dx, $dy];
        }
    }

    @file_put_contents($tmp, "<?php\nreturn " . var_export($pts, true) . ";\n");
    $mem[$r] = $pts;
    return $pts;
}

function draw_text_baseline_center($im, string $fontFile, float $fontSize, float $angleRad, float $cx, float $cy, int $fillColor, ?int $strokeColorInner, int $innerRadius, ?int $strokeColorOuter, int $outerRadius, string $text): void {
    if ($text === '') return;

    $angleDegGd = -rad2deg($angleRad); // GD: CCW; canvas rotate(): CW in screen coords
$bbox0 = imagettfbbox($fontSize, 0.0, $fontFile, $text);
    if ($bbox0 === false) return;
    $xs = [$bbox0[0], $bbox0[2], $bbox0[4], $bbox0[6]];
    $minx = min($xs);
    $maxx = max($xs);

    // Canvas `textAlign=center`: prefer centering by ink bbox at angle 0 for stable rotation parity.
    $centerX0 = ($minx + $maxx) / 2.0;


    $cos = cos($angleRad);
    $sin = sin($angleRad);

    $offX = $centerX0 * $cos;
    $offY = $centerX0 * $sin;

    $ox = $cx - $offX;
    $oy = $cy - $offY;

    if ($strokeColorOuter !== null && $outerRadius > 0) {
        foreach (circle_offsets($outerRadius) as [$dx, $dy]) {
            imagettftext($im, $fontSize, $angleDegGd, (int)round($ox + $dx), (int)round($oy + $dy), $strokeColorOuter, $fontFile, $text);
        }
    }
    if ($strokeColorInner !== null && $innerRadius > 0) {
        foreach (circle_offsets($innerRadius) as [$dx, $dy]) {
            imagettftext($im, $fontSize, $angleDegGd, (int)round($ox + $dx), (int)round($oy + $dy), $strokeColorInner, $fontFile, $text);
        }
    }
    imagettftext($im, $fontSize, $angleDegGd, (int)round($ox), (int)round($oy), $fillColor, $fontFile, $text);
}

function arcst_storage_dir(string $siteRoot): string {
    $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arcst_' . substr(sha1($siteRoot), 0, 10);
    if (!is_dir($base)) {
        @mkdir($base, 0775, true);
    }
    return $base;
}

function arcst_get_key(): string {
    $k = $_SERVER['HTTP_X_KEY'] ?? '';
    $k = is_string($k) ? trim($k) : '';
    if ($k !== '' && preg_match('/^[A-Fa-f0-9]{16,64}$/', $k)) return $k;
    return bin2hex(random_bytes(16));
}

function arcst_read_int(string $path, int $default = 0): int {
    if (!is_file($path)) return $default;
    $raw = @file_get_contents($path);
    if ($raw === false) return $default;
    $v = (int)trim($raw);
    return $v < 0 ? 0 : $v;
}

function arcst_write_int(string $path, int $value): bool {
    $tmp = $path . '.tmp';
    $ok = @file_put_contents($tmp, (string)$value, LOCK_EX);
    if ($ok === false) return false;
    return @rename($tmp, $path);
}

function arcst_inc_int(string $path, int $delta = 1): int {
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $fp = @fopen($path, 'c+');
    if ($fp === false) return 0;
    @flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $cur = (int)trim((string)$raw);
    if ($cur < 0) $cur = 0;
    $cur += $delta;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string)$cur);
    fflush($fp);
    @flock($fp, LOCK_UN);
    fclose($fp);
    return $cur;
}
