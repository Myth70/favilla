<?php

namespace App\Setup;

/**
 * SetupController — gestisce il flusso multi-step del wizard.
 * Standalone: nessuna dipendenza dal framework.
 *
 * Lo stato del wizard è salvato su file (storage/.setup_state.json)
 * invece che in sessione PHP, per evitare problemi di persistenza
 * sessione su configurazioni XAMPP/Windows.
 */
class SetupController
{
    private int $currentStep;
    private string $stateFile;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->stateFile   = BASE_PATH . '/storage/.setup_state.json';
        $this->currentStep = (int) ($_GET['step'] ?? 1);
        if ($this->currentStep < 1 || $this->currentStep > 6) {
            $this->currentStep = 1;
        }
    }

    // ------------------------------------------------------------------
    // State persistence (file-based)
    // ------------------------------------------------------------------

    private function saveState(string $key, array $data): void
    {
        $state = $this->loadAllState();
        $state[$key] = $data;
        file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function loadState(string $key): array
    {
        $state = $this->loadAllState();
        return $state[$key] ?? [];
    }

    private function loadAllState(): array
    {
        if (!file_exists($this->stateFile)) {
            return [];
        }
        return json_decode(file_get_contents($this->stateFile), true) ?? [];
    }

    private function clearState(): void
    {
        if (file_exists($this->stateFile)) {
            @unlink($this->stateFile);
        }
    }

    // ------------------------------------------------------------------
    // Entry point
    // ------------------------------------------------------------------

    public function handle(): void
    {
        // Gate: nessuna operazione (nemmeno il test DB) prima che l'operatore
        // dimostri accesso al filesystem del server inserendo il token di
        // installazione. Chiude la finestra pre-install a un attaccante remoto.
        $this->ensureAuthorized();

        // AJAX: test connessione DB
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_db') {
            $this->handleTestDbJson();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
            return;
        }

        // Guard: se si arriva al passo 6 via GET senza dati
        // (accesso diretto, refresh dopo errore) → torna al passo 1.
        if ($this->currentStep === 6) {
            $db    = $this->loadState('db');
            $app   = $this->loadState('app');
            $admin = $this->loadState('admin');
            if (
                empty($db['name'])    ||
                empty($app['appKey']) ||
                empty($admin['email'])
            ) {
                $this->redirect(1);
            }
        }

        $this->renderStep($this->currentStep);
    }

    // ------------------------------------------------------------------
    // Install-token gate
    // ------------------------------------------------------------------

    /**
     * Blocca l'intero wizard finché non viene fornito il token di
     * installazione salvato in storage/.setup_token (leggibile solo con
     * accesso al filesystem/console del server). Una volta sbloccato, lo
     * stato è persistito nello state file (come il resto del wizard) così
     * da restare affidabile anche su configurazioni con sessioni PHP
     * inaffidabili (XAMPP/Windows). Neutralizza sia il completamento non
     * autorizzato del setup sia la primitiva SSRF/port-scan del test DB.
     */
    private function ensureAuthorized(): void
    {
        if (!empty($this->loadState('gate')['unlocked'])) {
            return;
        }

        $token    = $this->installToken();
        $supplied = trim((string) ($_POST['setup_token'] ?? ''));

        if ($supplied !== '' && hash_equals($token, $supplied)) {
            $this->saveState('gate', ['unlocked' => true]);
            $this->redirect(1); // riparte pulito dal passo 1 (never)
        }

        $this->renderTokenGate($supplied !== '');
        exit;
    }

    /**
     * Restituisce il token di installazione, generandolo al primo accesso.
     * Il token viene anche scritto nel log PHP per comodità operativa.
     */
    private function installToken(): string
    {
        $file = BASE_PATH . '/storage/.setup_token';

        if (is_file($file)) {
            $existing = trim((string) @file_get_contents($file));
            if ($existing !== '') {
                return $existing;
            }
        }

        $token = bin2hex(random_bytes(16));
        @file_put_contents($file, $token, LOCK_EX);
        @chmod($file, 0600);
        error_log('[Favilla setup] Token di installazione in storage/.setup_token: ' . $token);

        return $token;
    }

    private function renderTokenGate(bool $wrong): void
    {
        http_response_code($wrong ? 403 : 401);
        $err = $wrong
            ? '<p style="color:#b00020">Token non valido. Riprova.</p>'
            : '';
        echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>Setup — Autorizzazione</title>'
           . '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;'
           . 'justify-content:center;min-height:100vh;background:#f0f2f5;margin:0}'
           . '.box{background:#fff;border-radius:12px;padding:2rem 2.5rem;max-width:480px;'
           . 'box-shadow:0 2px 16px rgba(0,0,0,.1)}h1{color:#1e3a5f;margin:0 0 .75rem;font-size:1.3rem}'
           . 'p{color:#495057;margin:.5rem 0;line-height:1.5}code{background:#f8f9fa;padding:.15rem .4rem;'
           . 'border-radius:4px;font-size:.9rem}input{width:100%;box-sizing:border-box;padding:.6rem;'
           . 'margin:.75rem 0;border:1px solid #ced4da;border-radius:8px;font-size:1rem}'
           . 'button{background:#1e3a5f;color:#fff;border:0;border-radius:8px;padding:.6rem 1.2rem;'
           . 'font-size:1rem;cursor:pointer}</style></head><body>'
           . '<div class="box"><h1>🔒 Autorizzazione richiesta</h1>'
           . '<p>Per motivi di sicurezza, apri sul server il file '
           . '<code>storage/.setup_token</code> e incolla qui il suo contenuto. '
           . 'Il token è stato anche scritto nel log PHP.</p>'
           . $err
           . '<form method="post" action="setup.php" autocomplete="off">'
           . '<input type="text" name="setup_token" placeholder="Token di installazione" '
           . 'autofocus required>'
           . '<button type="submit">Sblocca il setup</button></form></div></body></html>';
    }

    // ------------------------------------------------------------------
    // POST handler
    // ------------------------------------------------------------------

    private function handlePost(): void
    {
        $step = (int) ($_POST['step'] ?? 1);

        switch ($step) {
            case 1:
                $checks = SetupValidator::checkRequirements();
                $allOk  = array_reduce($checks, fn ($c, $i) => $c && $i['ok'], true);
                if (!$allOk) {
                    $this->saveState('errors', ['Correggi i requisiti indicati con ✗ prima di continuare.']);
                    $this->redirect(1);
                }
                $this->redirect(2);
                break;

            case 2:
                $host = trim($_POST['db_host'] ?? 'localhost');
                $port = trim($_POST['db_port'] ?? '3306');
                $name = trim($_POST['db_name'] ?? '');
                $user = trim($_POST['db_user'] ?? '');
                $pass = $_POST['db_pass'] ?? '';

                $result = SetupValidator::testDbConnection($host, $port, $name, $user, $pass);
                if ($result !== true) {
                    $this->saveState('errors', ["Connessione DB fallita: $result"]);
                    $this->saveState('db', compact('host', 'port', 'name', 'user', 'pass'));
                    $this->redirect(2);
                }
                $this->saveState('db', compact('host', 'port', 'name', 'user', 'pass'));
                $this->redirect(3);
                break;

            case 3:
                $appName  = trim($_POST['app_name'] ?? 'Favilla');
                $location = $this->normalizeAppLocation(
                    trim((string) ($_POST['app_url'] ?? '')),
                    trim((string) ($_POST['app_base_path'] ?? ''))
                );
                $appUrl      = $location['url'];
                $appBasePath = $location['base_path'];
                $appEnv   = in_array($_POST['app_env'] ?? '', ['development', 'production'], true)
                            ? $_POST['app_env'] : 'production';
                $appKey   = trim($_POST['app_key'] ?? '');
                $timezone = trim($_POST['timezone'] ?? 'Europe/Rome');

                $errors = [];
                if ($location['error'] !== null) {
                    $errors[] = $location['error'];
                }
                if (!SetupValidator::validateAppKey($appKey)) {
                    $errors[] = 'APP_KEY deve essere di almeno 32 caratteri.';
                }

                if ($errors) {
                    $this->saveState('errors', $errors);
                    $this->saveState('app', compact('appName', 'appUrl', 'appBasePath', 'appEnv', 'appKey', 'timezone'));
                    $this->redirect(3);
                }
                $this->saveState('app', compact('appName', 'appUrl', 'appBasePath', 'appEnv', 'appKey', 'timezone'));
                $this->redirect(4);
                break;

            case 4:
                $validEditions = array_keys(config('editions.profiles', []));
                $edition = $_POST['edition'] ?? '';
                if (!in_array($edition, $validEditions, true)) {
                    $edition = config('editions.default', 'developer');
                }
                $this->saveState('edition', compact('edition'));
                $this->saveState('demo', ['load' => isset($_POST['demo_data'])]);
                $this->redirect(5);

                // no break
            case 5:
                $name     = trim($_POST['admin_name'] ?? '');
                $email    = trim($_POST['admin_email'] ?? '');
                $username = trim($_POST['admin_username'] ?? '');
                $password = $_POST['admin_password'] ?? '';
                $confirm  = $_POST['admin_confirm'] ?? '';

                $errors = [];
                if (empty($name)) {
                    $errors[] = 'Il nome è obbligatorio.';
                }
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Email non valida.';
                }
                if (empty($username)) {
                    $errors[] = 'Lo username è obbligatorio.';
                }
                $pwErrors = SetupValidator::validatePassword($password);
                $errors   = array_merge($errors, $pwErrors);
                if ($password !== $confirm) {
                    $errors[] = 'Le due password non corrispondono.';
                }

                if ($errors) {
                    $this->saveState('errors', $errors);
                    $this->saveState('admin', compact('name', 'email', 'username'));
                    $this->redirect(5);
                }
                $this->saveState('admin', compact('name', 'email', 'username', 'password'));
                $this->redirect(6);
                break;

            default:
                $this->redirect(1);
        }
    }

    // ------------------------------------------------------------------
    // AJAX: test DB
    // ------------------------------------------------------------------

    private function handleTestDbJson(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $host = trim($_POST['host'] ?? 'localhost');
        $port = trim($_POST['port'] ?? '3306');
        $name = trim($_POST['name'] ?? '');
        $user = trim($_POST['user'] ?? '');
        $pass = $_POST['pass'] ?? '';

        $result = SetupValidator::testDbConnection($host, $port, $name, $user, $pass);
        if ($result === true) {
            echo json_encode(['ok' => true,  'message' => 'Connessione riuscita!']);
        } else {
            echo json_encode(['ok' => false, 'message' => $result]);
        }
        exit;
    }

    // ------------------------------------------------------------------
    // Rendering
    // ------------------------------------------------------------------

    private function renderStep(int $step): void
    {
        $errors = $this->loadState('errors');
        // Consumo one-shot: cancella gli errori dopo averli letti
        if (!empty($errors)) {
            $state = $this->loadAllState();
            unset($state['errors']);
            file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $data = $this->getStepData($step);
        $data['errors']      = $errors;
        $data['currentStep'] = $step;

        $slug     = $this->stepSlug($step);
        $stepFile = __DIR__ . '/steps/0' . $step . '_' . $slug . '.php';

        ob_start();
        extract($data);
        include $stepFile;
        $content = ob_get_clean();

        include __DIR__ . '/steps/layout.php';
    }

    private function getStepData(int $step): array
    {
        return match ($step) {
            1 => ['checks'  => SetupValidator::checkRequirements()],
            2 => ['db'      => $this->loadState('db')],
            3 => ['app'     => $this->loadState('app')],
            4 => ['edition' => $this->loadState('edition'), 'demo' => $this->loadState('demo')],
            5 => ['admin'   => $this->loadState('admin')],
            6 => $this->runSetupComplete(),
            default => [],
        };
    }

    private function stepSlug(int $step): string
    {
        return match ($step) {
            1 => 'requirements',
            2 => 'database',
            3 => 'application',
            4 => 'edizione',
            5 => 'admin',
            6 => 'complete',
            default => 'requirements',
        };
    }

    // ------------------------------------------------------------------
    // Step 6 — esecuzione automatica
    // ------------------------------------------------------------------

    private function runSetupComplete(): array
    {
        $log   = [];
        $error = null;

        try {
            $db    = $this->loadState('db');
            $app   = $this->loadState('app');
            $admin = $this->loadState('admin');

            // Validazione dati minimi
            if (empty($db['name']) || empty($app['appKey']) || empty($admin['email'])) {
                throw new \RuntimeException(
                    'Dati setup incompleti. Torna al passo 1 e ricomincia.'
                );
            }

            // 1. Scrivi .env
            $appDebug = $app['appEnv'] === 'development' ? 'true' : 'false';
            $envVars  = [
                'APP_NAME'  => '"' . str_replace('"', '\\"', $app['appName'] ?? 'Favilla') . '"',
                'APP_ENV'   => $app['appEnv']  ?? 'production',
                'APP_DEBUG' => $appDebug,
                'APP_URL'   => $app['appUrl']  ?? '',
                'APP_BASE_PATH' => $app['appBasePath'] ?? '',
                'APP_KEY'   => $app['appKey']  ?? '',
                'APP_TIMEZONE' => $app['timezone'] ?? 'Europe/Rome',
                // Genera una chiave di cifratura backup dedicata: senza, gli archivi
                // verrebbero salvati in chiaro. È indipendente da APP_KEY by design.
                'BACKUP_ENCRYPTION_KEY' => bin2hex(random_bytes(32)),
                'DB_HOST'   => $db['host']     ?? 'localhost',
                'DB_PORT'   => $db['port']     ?? '3306',
                'DB_NAME'   => $db['name']     ?? '',
                'DB_USER'   => $db['user']     ?? '',
                'DB_PASS'   => $db['pass']     ?? '',
            ];

            $written = SetupValidator::writeEnvFile(BASE_PATH . '/.env.example', $envVars);
            if (!$written) {
                throw new \RuntimeException(
                    'Impossibile scrivere il file .env. Verifica i permessi della directory.'
                );
            }
            $log[] = '✓ File .env scritto';

            // 2. Esegui fresh install: schema.sql + seeds + migration progressive
            $phpBin = $this->findPhpCli();
            $migrateCmd = escapeshellarg($phpBin) . ' ' . escapeshellarg(BASE_PATH . '/database/migrate.php') . ' --fresh';
            $output     = [];
            $returnCode = 0;
            exec($migrateCmd . ' 2>&1', $output, $returnCode);
            if ($returnCode !== 0) {
                throw new \RuntimeException(
                    'Setup database fallito: ' . implode(' | ', array_slice($output, -3))
                );
            }
            $log[] = '✓ Schema database creato e dati iniziali inseriti';

            // 3. Inserisci / aggiorna utente admin
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $db['host'],
                $db['port'],
                $db['name']
            );
            $pdo = new \PDO($dsn, $db['user'], $db['pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            // Edizione scelta al passo 4 (default developer se lo stato manca).
            // La riga 'app_edition' esiste già (seeds/required.sql, appena eseguito
            // da migrate --fresh): qui la aggiorniamo con la scelta effettiva.
            $edition = $this->loadState('edition')['edition'] ?? config('editions.default', 'developer');
            $pdo->prepare(
                "UPDATE app_settings SET `value` = ? WHERE `key` = 'app_edition'"
            )->execute([$edition]);
            $log[] = '✓ Edizione impostata: ' . $edition;

            // Edizione Team: abilita automaticamente i moduli opzionali dedicati
            // (Progetti/Teams/Documenti/Blog), seedati disabilitati di default in
            // seeds/required.sql. Personal/developer restano disabilitati: si abilitano
            // a mano da Admin -> Moduli (percorso di upgrade).
            $defaultEnabledModules = config("editions.profiles.{$edition}.default_enabled_modules", []);
            if (!empty($defaultEnabledModules)) {
                $enableStmt = $pdo->prepare(
                    'UPDATE module_states SET enabled = 1 WHERE name = ?'
                );
                foreach ($defaultEnabledModules as $moduleName) {
                    $enableStmt->execute([$moduleName]);
                }
                $log[] = '✓ Moduli edizione Team abilitati: ' . implode(', ', $defaultEnabledModules);
            }

            $hash = password_hash($admin['password'], PASSWORD_ARGON2ID);

            // migrate --fresh seeds a bootstrap admin (the only existing user) with a
            // publicly-known default password. Repurpose THAT row with the operator's
            // identity so no default credential ever survives — instead of inserting a
            // second admin alongside it. must_change_password=0: it's the operator's own
            // password. A defensive INSERT covers the (unexpected) no-seed case.
            $bootstrap = $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
            if ($bootstrap) {
                $upd = $pdo->prepare(
                    'UPDATE users SET name = ?, email = ?, username = ?, password = ?,
                            is_active = 1, must_change_password = 0, updated_at = NOW()
                     WHERE id = ?'
                );
                $upd->execute([$admin['name'], $admin['email'], $admin['username'], $hash, $bootstrap['id']]);
                $userId = (int) $bootstrap['id'];
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO users (name, email, username, password, is_active, must_change_password, created_at, updated_at)
                     VALUES (?, ?, ?, ?, 1, 0, NOW(), NOW())'
                );
                $ins->execute([$admin['name'], $admin['email'], $admin['username'], $hash]);
                $userId = (int) $pdo->lastInsertId();
            }
            $log[] = '✓ Utente amministratore creato';

            // 4. Seed ruoli se tabella vuota (deve precedere l'assegnazione)
            $roleCount = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
            if ($roleCount === 0) {
                $pdo->exec(
                    "INSERT IGNORE INTO roles (name, slug, description) VALUES
                     ('Administrator', 'admin',   'Accesso completo al sistema'),
                     ('Manager',       'manager', 'Gestione operativa'),
                     ('User',          'user',    'Utente standard')"
                );
                $log[] = '✓ Ruoli base creati';
            }

            // 5. Assegna ruolo admin
            $adminRoleId = $pdo->query("SELECT id FROM roles WHERE slug = 'admin' LIMIT 1")
                              ->fetchColumn();
            if ($adminRoleId) {
                $pdo->prepare('INSERT IGNORE INTO user_role (user_id, role_id) VALUES (?, ?)')
                    ->execute([$userId, $adminRoleId]);
                $log[] = '✓ Ruolo Administrator assegnato';
            } else {
                throw new \RuntimeException('Ruolo "admin" non trovato dopo il seed. Contatta il supporto.');
            }

            // 5b. Dati dimostrativi, se richiesti al passo 4. Un errore qui non
            // deve bloccare l'installazione: log di avviso e si prosegue.
            if (!empty($this->loadState('demo')['load'])) {
                try {
                    $summary = (new DemoSeeder($pdo, BASE_PATH))->run();
                    $loaded  = count(array_filter($summary['sections'], static fn (string $s): bool => $s === 'ok'));
                    $log[]   = "✓ Dati dimostrativi caricati ({$loaded} sezioni, {$summary['files_copied']} file)";
                } catch (\Throwable $demoError) {
                    $log[] = '⚠ Dati dimostrativi non caricati: ' . $demoError->getMessage()
                        . ' (riprova con: php favilla demo:seed)';
                }
            }

            // 6. Crea marker setup_complete
            file_put_contents(BASE_PATH . '/storage/.setup_complete', date('Y-m-d H:i:s'));
            $log[] = '✓ Setup completato con successo';

            // Pulisci stato wizard
            $this->clearState();

        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return compact('log', 'error');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Trova il binario PHP CLI, anche quando PHP gira come modulo Apache.
     */
    private function findPhpCli(): string
    {
        // 1. Se PHP_BINARY punta a php.exe (o php) CLI, usalo
        $bin = PHP_BINARY;
        if ($bin && is_file($bin) && stripos(basename($bin), 'php') === 0 && stripos(basename($bin), 'httpd') === false) {
            // Verifica che sia effettivamente CLI e non CGI con un nome simile
            $dir = dirname($bin);
            // Preferisci php.exe esplicito nella stessa directory
            foreach (['php.exe', 'php'] as $name) {
                $candidate = $dir . DIRECTORY_SEPARATOR . $name;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
            return $bin;
        }

        // 2. XAMPP Windows: cerca in directory standard
        if (DIRECTORY_SEPARATOR === '\\') {
            $xamppPaths = [
                'C:\\xampp\\php\\php.exe',
                dirname(dirname(BASE_PATH)) . '\\php\\php.exe', // relativo a htdocs
            ];
            foreach ($xamppPaths as $path) {
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        // 3. Fallback: cerca nel PATH di sistema
        $which = DIRECTORY_SEPARATOR === '\\' ? 'where php 2>NUL' : 'which php 2>/dev/null';
        $found = trim((string) shell_exec($which));
        if ($found) {
            // 'where' su Windows può restituire più righe
            $first = strtok($found, "\n\r");
            if ($first && is_file($first)) {
                return $first;
            }
        }

        // 4. Ultimo tentativo
        return PHP_BINARY ?: 'php';
    }

    /**
     * @return array{url:string, base_path:string, error:?string}
     */
    private function normalizeAppLocation(string $appUrl, string $appBasePath): array
    {
        $appUrl = rtrim(trim($appUrl), '/');
        $appBasePath = trim($appBasePath);

        if ($appUrl === '') {
            return ['url' => '', 'base_path' => '', 'error' => 'APP_URL è obbligatorio.'];
        }

        $parsed = parse_url($appUrl);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            return ['url' => $appUrl, 'base_path' => $appBasePath, 'error' => 'APP_URL deve essere un URL assoluto valido.'];
        }

        $normalizedUrl = $parsed['scheme'] . '://' . $parsed['host'];
        if (!empty($parsed['port'])) {
            $normalizedUrl .= ':' . $parsed['port'];
        }

        $pathFromUrl = trim((string) ($parsed['path'] ?? ''), '/');
        $basePath = trim($appBasePath, '/');

        if ($basePath === '') {
            $basePath = $pathFromUrl;
        }

        if ($basePath !== '' && str_contains($basePath, ' ')) {
            return ['url' => $normalizedUrl, 'base_path' => $basePath, 'error' => 'APP_BASE_PATH non può contenere spazi.'];
        }

        return [
            'url' => $normalizedUrl,
            'base_path' => $basePath !== '' ? '/' . $basePath : '',
            'error' => null,
        ];
    }

    private function redirect(int $step): never
    {
        header('Location: setup.php?step=' . $step);
        exit;
    }
}
