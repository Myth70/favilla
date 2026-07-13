<?php

declare(strict_types=1);

/**
 * Genera favicon.ico e le icone PWA dal master raster del logo:
 *   docs/logo/favilla-mark-1024.png   (1024x1024, sfondo trasparente)
 *
 * Il vettoriale ufficiale è public/favicon.svg (trace del logo originale,
 * docs/logo/favilla_logo.jpg): i suoi path sono troppo complessi per essere
 * rasterizzati con GD, quindi il master PNG committato — esportato dallo
 * stesso SVG — fa da sorgente e qui si fa solo ridimensionamento con GD
 * (niente Imagick su XAMPP).
 *
 * Output (committati in repo):
 *   public/favicon.ico                               16/24/32/48/64/128/256 (PNG-in-ICO)
 *   public/assets/img/pwa/icon-192.png               trasparente, purpose "any"
 *   public/assets/img/pwa/icon-512.png               trasparente, purpose "any"
 *   public/assets/img/pwa/icon-maskable-512.png      fondo pieno, safe zone maskable
 *   public/assets/img/pwa/apple-touch-icon-180.png   fondo pieno (iOS)
 *
 * Uso:
 *   php tools/generate-pwa-icons.php
 *   php tools/generate-pwa-icons.php --preview=out.png [--size=512]   # anteprima su fondo bianco
 */

// Colore del cerchio del logo: usato come fondo pieno per maskable/iOS,
// così il cerchio si fonde con il canvas e la fiamma resta in safe zone.
const CIRCLE_COLOR = [0x19, 0x33, 0x3A];

function loadMaster(string $file): \GdImage
{
    $img = imagecreatefrompng($file);
    if ($img === false) {
        fwrite(STDERR, "[ERRORE] Master non leggibile: {$file}\n");
        exit(1);
    }
    imagesavealpha($img, true);
    return $img;
}

/**
 * @param array{int,int,int}|null $background RGB di sfondo, null = trasparente
 */
function renderIcon(\GdImage $master, int $size, float $logoRatio, ?array $background): \GdImage
{
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    if ($background === null) {
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    } else {
        imagefill($img, 0, 0, imagecolorallocate($img, ...$background));
    }
    imagealphablending($img, true);

    $dst = (int) round($size * $logoRatio);
    $off = intdiv($size - $dst, 2);
    imagecopyresampled($img, $master, $off, $off, 0, 0, $dst, $dst, imagesx($master), imagesy($master));

    imagealphablending($img, false);
    imagesavealpha($img, true);
    return $img;
}

/**
 * @param array{int,int,int}|null $background
 */
function writePng(\GdImage $master, int $size, float $logoRatio, ?array $background, string $outFile): void
{
    $img = renderIcon($master, $size, $logoRatio, $background);
    imagepng($img, $outFile, 6);
    imagedestroy($img);
    echo "  [OK] {$outFile}\n";
}

/**
 * Impacchetta PNG a 32bpp in un contenitore .ico (voci PNG, supportate da Vista+).
 *
 * @param int[] $sizes
 */
function writeIco(\GdImage $master, array $sizes, string $outFile): void
{
    $blobs = [];
    foreach ($sizes as $size) {
        $img = renderIcon($master, $size, 1.0, null);
        ob_start();
        imagepng($img, null, 9);
        $blobs[$size] = (string) ob_get_clean();
        imagedestroy($img);
    }

    $count = count($blobs);
    $header = pack('vvv', 0, 1, $count);
    $entries = '';
    $data = '';
    $offset = 6 + 16 * $count;
    foreach ($blobs as $size => $blob) {
        $dim = $size >= 256 ? 0 : $size;
        $entries .= pack('CCCCvvVV', $dim, $dim, 0, 0, 1, 32, strlen($blob), $offset);
        $offset += strlen($blob);
        $data .= $blob;
    }

    file_put_contents($outFile, $header . $entries . $data);
    echo "  [OK] {$outFile} (" . implode(', ', array_keys($blobs)) . "px)\n";
}

$root = dirname(__DIR__);
$master = loadMaster($root . '/docs/logo/favilla-mark-1024.png');

$options = getopt('', ['preview::', 'size::']);
if (isset($options['preview'])) {
    $out = is_string($options['preview']) && $options['preview'] !== '' ? $options['preview'] : $root . '/logo-preview.png';
    $size = isset($options['size']) ? max(16, (int) $options['size']) : 512;
    writePng($master, $size, 1.0, [0xFF, 0xFF, 0xFF], $out);
    exit(0);
}

$outDir = $root . '/public/assets/img/pwa';
if (!is_dir($outDir) && !mkdir($outDir, 0775, true)) {
    fwrite(STDERR, "[ERRORE] Impossibile creare {$outDir}\n");
    exit(1);
}

echo "Generazione icone PWA + favicon.ico...\n";
writePng($master, 192, 1.0, null, $outDir . '/icon-192.png');
writePng($master, 512, 1.0, null, $outDir . '/icon-512.png');
writePng($master, 512, 0.72, CIRCLE_COLOR, $outDir . '/icon-maskable-512.png');
writePng($master, 180, 1.0, CIRCLE_COLOR, $outDir . '/apple-touch-icon-180.png');
writeIco($master, [16, 24, 32, 48, 64, 128, 256], $root . '/public/favicon.ico');
echo "Completato.\n";
