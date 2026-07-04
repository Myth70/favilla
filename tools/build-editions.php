<?php

/**
 * Build the 3 Favilla release zips (Developer / Personal / Team) from the
 * current git checkout.
 *
 * Operates on whatever is currently checked out — HEAD for a local dry run,
 * or the tag ref checked out by CI for a real release. It does NOT switch
 * branches/tags itself, to avoid mutating a developer's working tree.
 *
 * Usage:
 *   php tools/build-editions.php <version> [--out=dist]
 *
 * Produces:
 *   dist/favilla-<version>-developer.zip — full tracked tree, INCLUDING the
 *       dev-only paths normally hidden from distribution archives (CLAUDE.md,
 *       docs/contracts/, context/, app/Modules/_Template, tools/, tests/, ...).
 *       Built from `git ls-files`, which — unlike `git archive` — does not
 *       apply .gitattributes export-ignore rules.
 *   dist/favilla-<version>-personal.zip  — `git archive` (export-ignore
 *       applied, so the paths above are absent) with app/Config/editions.php's
 *       `'default' => 'developer'` rewritten to `'personal'`, PLUS the local
 *       vendor/ tree bundled in, so the zip is unzip-and-go (no Composer
 *       required on the target server).
 *   dist/favilla-<version>-team.zip      — same, rewritten to `'team'`.
 *
 * vendor/ is excluded from the Developer zip only (contributors run
 * `composer install` anyway). For Personal/Team the CALLER must have run
 * `composer install --no-dev --optimize-autoloader` first — the script aborts
 * if vendor/ is missing or still contains dev dependencies (override the dev
 * check for local dry runs with --allow-dev-vendor).
 */

$basePath = dirname(__DIR__);
chdir($basePath);

$version = $argv[1] ?? null;
if ($version === null || $version === '' || str_starts_with($version, '--')) {
    fwrite(STDERR, "Usage: php tools/build-editions.php <version> [--out=dist]\n");
    exit(1);
}

$outDir = 'dist';
$allowDevVendor = false;
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $outDir = substr($arg, strlen('--out='));
    }
    if ($arg === '--allow-dev-vendor') {
        $allowDevVendor = true;
    }
}

// vendor/ finisce dentro ai zip Personal/Team: deve esistere e (salvo dry run
// locale con --allow-dev-vendor) essere un install --no-dev, per non spedire
// phpunit/phpstan/php-cs-fixer agli utenti finali.
if (!is_file($basePath . '/vendor/autoload.php')) {
    fwrite(STDERR, "vendor/autoload.php mancante: esegui `composer install --no-dev --optimize-autoloader` prima del build.\n");
    exit(1);
}
if (!$allowDevVendor && is_dir($basePath . '/vendor/phpunit')) {
    fwrite(STDERR, "vendor/ contiene dipendenze dev (vendor/phpunit): esegui `composer install --no-dev --optimize-autoloader`,\n"
        . "oppure usa --allow-dev-vendor per un dry run locale.\n");
    exit(1);
}

if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Impossibile creare la directory di output: {$outDir}\n");
    exit(1);
}

/**
 * Run a git subcommand, echoing stderr and aborting the build on failure.
 */
function runGit(string $cmd): string
{
    $output = [];
    $code   = 0;
    exec('git ' . $cmd . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "Comando fallito: git {$cmd}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
    return implode("\n", $output);
}

/**
 * Add the whole vendor/ tree to an open zip, preserving relative paths.
 * Returns the number of files added.
 */
function addVendorToZip(ZipArchive $zip, string $basePath): int
{
    $vendorPath = $basePath . '/vendor';
    $iterator   = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($vendorPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $added = 0;
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $relative = 'vendor/' . str_replace('\\', '/', substr($file->getPathname(), strlen($vendorPath) + 1));
        if ($zip->addFile($file->getPathname(), $relative)) {
            $added++;
        }
    }
    return $added;
}

echo "== Favilla release build — versione {$version} ==\n\n";

// ─── 1. Developer zip — git ls-files (export-ignore NON si applica) ─────────

echo "[1/3] Developer zip...\n";

$files = array_values(array_filter(explode("\n", runGit('ls-files'))));
if (empty($files)) {
    fwrite(STDERR, "Nessun file tracciato da git trovato: repo senza commit?\n");
    exit(1);
}

$devZipPath = $outDir . "/favilla-{$version}-developer.zip";
if (file_exists($devZipPath)) {
    unlink($devZipPath);
}

$zip = new ZipArchive();
if ($zip->open($devZipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Impossibile creare {$devZipPath}\n");
    exit(1);
}
foreach ($files as $file) {
    $fullPath = $basePath . '/' . $file;
    if (is_file($fullPath)) {
        $zip->addFile($fullPath, $file);
    }
}
$zip->close();

echo '  OK: ' . $devZipPath . ' (' . count($files) . " file)\n\n";

// ─── 2 & 3. Personal / Team zip — git archive (export-ignore applicato) ─────

$editionsConfigPath = 'app/Config/editions.php';
$step = 2;
foreach (['personal', 'team'] as $edition) {
    echo "[{$step}/3] {$edition} zip...\n";
    $step++;

    $zipPath = $outDir . "/favilla-{$version}-{$edition}.zip";
    if (file_exists($zipPath)) {
        unlink($zipPath);
    }

    runGit('archive --format=zip -o ' . escapeshellarg($zipPath) . ' HEAD');

    // Riscrivi 'default' => 'developer' con l'edizione target dentro allo zip
    // appena generato (il commento in editions.php avvisa che il build la
    // riscrive — non toccare il file sorgente sul disco).
    $editionZip = new ZipArchive();
    if ($editionZip->open($zipPath) !== true) {
        fwrite(STDERR, "Impossibile riaprire {$zipPath} per la riscrittura di {$editionsConfigPath}\n");
        exit(1);
    }

    $content = $editionZip->getFromName($editionsConfigPath);
    if ($content === false) {
        fwrite(STDERR, "{$editionsConfigPath} non trovato in {$zipPath}\n");
        exit(1);
    }

    $rewritten = preg_replace(
        "/'default'\s*=>\s*'developer'/",
        "'default' => '{$edition}'",
        $content,
        1,
        $replacements
    );
    if ($replacements !== 1) {
        fwrite(STDERR, "Riga \"'default' => 'developer'\" non trovata in {$editionsConfigPath} — build interrotto.\n");
        exit(1);
    }

    $editionZip->addFromString($editionsConfigPath, $rewritten);

    // Bundle vendor/ so the zip installs without Composer on the target server.
    $vendorFiles = addVendorToZip($editionZip, $basePath);

    $editionZip->close();

    echo "  OK: {$zipPath} (vendor: {$vendorFiles} file)\n\n";
}

echo "Fatto. Zip generati in {$outDir}/\n";
