<?php

declare(strict_types=1);

/**
 * Genera le icone PWA rasterizzando la fiamma di public/favicon.svg con GD
 * (niente Imagick su XAMPP): le curve cubiche del SVG sono campionate in
 * poligoni e disegnate con supersampling 4x per l'antialiasing.
 *
 * Output (committati in repo):
 *   public/assets/img/pwa/icon-192.png              trasparente, purpose "any"
 *   public/assets/img/pwa/icon-512.png              trasparente, purpose "any"
 *   public/assets/img/pwa/icon-maskable-512.png     fondo pieno, safe zone maskable
 *   public/assets/img/pwa/apple-touch-icon-180.png  fondo pieno (iOS)
 *
 * Uso: php tools/generate-pwa-icons.php
 */

// Path della fiamma copiati da public/favicon.svg (viewBox 0 0 32 32).
// Ogni path: punto iniziale + lista di curve cubiche [c1x,c1y, c2x,c2y, x,y].
const FLAME_PATHS = [
    [
        'color' => [0xF9, 0x73, 0x16], // #f97316 outer
        'start' => [16.0, 2.0],
        'curves' => [
            [13.0, 7.0, 5.0, 10.0, 5.0, 18.0],
            [5.0, 24.8, 9.8, 29.5, 16.0, 30.0],
            [22.2, 29.5, 27.0, 24.8, 27.0, 18.0],
            [27.0, 10.0, 19.0, 7.0, 16.0, 2.0],
        ],
    ],
    [
        'color' => [0xEA, 0x58, 0x0C], // #ea580c mid
        'start' => [16.0, 9.0],
        'curves' => [
            [14.0, 13.0, 10.0, 16.0, 10.0, 20.0],
            [10.0, 23.9, 12.7, 27.0, 16.0, 27.0],
            [19.3, 27.0, 22.0, 23.9, 22.0, 20.0],
            [22.0, 16.0, 18.0, 13.0, 16.0, 9.0],
        ],
    ],
    [
        'color' => [0xFB, 0xBF, 0x24], // #fbbf24 core
        'start' => [16.0, 15.0],
        'curves' => [
            [15.0, 17.0, 13.0, 19.0, 14.0, 22.0],
            [14.7, 24.0, 16.0, 25.0, 16.0, 25.0],
            [16.0, 25.0, 17.3, 24.0, 18.0, 22.0],
            [19.0, 19.0, 17.0, 17.0, 16.0, 15.0],
        ],
    ],
];

const SVG_CENTER = 16.0;   // centro del viewBox
const SVG_FLAME_HEIGHT = 28.0; // la fiamma copre y 2..30
const SUPERSAMPLE = 4;

/**
 * @param float[] $p0
 * @return array<int, float[]>
 */
function sampleCubic(array $p0, float $c1x, float $c1y, float $c2x, float $c2y, float $x, float $y, int $steps = 48): array
{
    $points = [];
    for ($i = 1; $i <= $steps; $i++) {
        $t = $i / $steps;
        $mt = 1 - $t;
        $px = ($mt ** 3) * $p0[0] + 3 * ($mt ** 2) * $t * $c1x + 3 * $mt * ($t ** 2) * $c2x + ($t ** 3) * $x;
        $py = ($mt ** 3) * $p0[1] + 3 * ($mt ** 2) * $t * $c1y + 3 * $mt * ($t ** 2) * $c2y + ($t ** 3) * $y;
        $points[] = [$px, $py];
    }
    return $points;
}

/**
 * Disegna la fiamma su un canvas GD già allocato.
 *
 * @param resource|\GdImage $img
 */
function drawFlame(\GdImage $img, float $scale, float $offsetX, float $offsetY): void
{
    foreach (FLAME_PATHS as $path) {
        $points = [$path['start']];
        $current = $path['start'];
        foreach ($path['curves'] as $curve) {
            $sampled = sampleCubic($current, ...$curve);
            $points = array_merge($points, $sampled);
            $current = [$curve[4], $curve[5]];
        }

        $flat = [];
        foreach ($points as $p) {
            $flat[] = $p[0] * $scale + $offsetX;
            $flat[] = $p[1] * $scale + $offsetY;
        }

        [$r, $g, $b] = $path['color'];
        $color = imagecolorallocate($img, $r, $g, $b);
        imagefilledpolygon($img, $flat, $color);
    }
}

/**
 * @param array{int,int,int}|null $background RGB di sfondo, null = trasparente
 */
function renderIcon(int $size, float $flameHeightRatio, ?array $background, string $outFile): void
{
    $big = $size * SUPERSAMPLE;
    $img = imagecreatetruecolor($big, $big);
    imagealphablending($img, false);

    if ($background === null) {
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
    } else {
        [$r, $g, $b] = $background;
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
    }
    imagealphablending($img, true);

    $flameHeightPx = $big * $flameHeightRatio;
    $scale = $flameHeightPx / SVG_FLAME_HEIGHT;
    // La fiamma è centrata su (16,16) nel viewBox (x 5..27, y 2..30): lo
    // stesso offset centra entrambi gli assi.
    $offset = $big / 2 - SVG_CENTER * $scale;
    drawFlame($img, $scale, $offset, $offset);

    $final = imagecreatetruecolor($size, $size);
    imagealphablending($final, false);
    imagesavealpha($final, true);
    $transparentFinal = imagecolorallocatealpha($final, 0, 0, 0, 127);
    imagefill($final, 0, 0, $transparentFinal);
    imagecopyresampled($final, $img, 0, 0, 0, 0, $size, $size, $big, $big);

    imagepng($final, $outFile, 6);
    imagedestroy($img);
    imagedestroy($final);

    echo "  [OK] {$outFile}\n";
}

$outDir = dirname(__DIR__) . '/public/assets/img/pwa';
if (!is_dir($outDir) && !mkdir($outDir, 0775, true)) {
    fwrite(STDERR, "[ERRORE] Impossibile creare {$outDir}\n");
    exit(1);
}

echo "Generazione icone PWA...\n";
renderIcon(192, 0.90, null, $outDir . '/icon-192.png');
renderIcon(512, 0.90, null, $outDir . '/icon-512.png');
renderIcon(512, 0.55, [0xFF, 0xF7, 0xED], $outDir . '/icon-maskable-512.png');
renderIcon(180, 0.62, [0xFF, 0xF7, 0xED], $outDir . '/apple-touch-icon-180.png');
echo "Completato.\n";
