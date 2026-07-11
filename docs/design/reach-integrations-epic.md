# Epic: Reach & Integrazioni (PWA/Web Push · API pubblica · Webhook)

> **Stato:** ✅ **implementato** (branch `feature/reach-integrations`) — tutte e tre le slice sono in codice, con hardening di sicurezza successivo. Questo documento resta il *razionale di design*; il contratto operativo vive nei doc qui sotto.
> **Scopo:** una capacità *trasversale* che aumenta il valore di ogni modulo, senza toccare le superfici framework. Tre slice indipendenti ma sinergici.
> **Doc collegati:** reference sviluppatori [`docs/api/README.md`](../api/README.md) · spec [`docs/api/openapi.json`](../api/openapi.json) · seam nei contratti [`docs/contracts/integrations.md`](../contracts/integrations.md) §12–14 · guide utente multilingua nel Help Online (`database/help/{api,webhooks,notifications}.json`).
> **Entry point del repo:** [`CLAUDE.md`](../../CLAUDE.md) · contratti in [`docs/contracts/`](../contracts/).

## Delta implementazione vs design (cosa è cambiato)

Le decisioni aperte (§5) chiuse in implementazione, più le divergenze utili da conoscere:

- **D2 — crypto Web Push:** si usa `minishlink/web-push` per encryption/VAPID **ma** la generazione della chiave EC effimera è incapsulata in `OpensslEcKeyFactory` (la `WebPush`/keygen "bare" della libreria fallisce su Windows/XAMPP senza `openssl.cnf` esplicito). `WebPushSender` orchestra i primitivi a mano invece di usare la classe `WebPush`.
- **D7 — collocazione API:** creato un **modulo `Api` dedicato** (PAT, middleware, envelope, `/me`, `/openapi.json`) + controller `Api\` **per-modulo** (Tasks, Contacts) che riusano i Service. `ApiTokenMiddleware`/`ApiRateLimitMiddleware` vivono **dentro il modulo `Api`** (non in `app/Middleware/`, off-limits).
- **D5 — anti-SSRF:** oltre alla blocklist IP privati, l'hardening ha aggiunto **normalizzazione IPv4-mapped IPv6** (fix bypass `::ffff:<hex>`), range riservati estesi (CGNAT, NAT64, multicast…) e **IP-pinning** (cURL `CURLOPT_RESOLVE`) che **chiude la finestra TOCTOU di DNS-rebinding** — nel design era ancora un rischio residuo.
- **Firma webhook:** non è più `sha256=<hmac(body)>` ma include un **timestamp** anti-replay (`X-Favilla-Signature: t=<unix>,v1=<hmac di {ts}.{body}>` + `X-Favilla-Timestamp`, verifica con finestra di tolleranza).
- **Scope token:** resi **obbligatori** (una selezione vuota non eredita più tutti i permessi — era un footgun di least-privilege).
- **Scheduler:** `webhooks:dispatch` seedato in `scheduler_jobs` (ogni 5 min) con **claim atomico per-riga** (stato `processing` + `locked_at`) per evitare il double-send tra run concorrenti.
- **D1/segreti a riposo:** mantenuti in chiaro come i token Telegram (chiave privata VAPID, secret HMAC) — ma **esclusi dal log di audit** e non più over-fetchati nelle view.
- **D6 — gating per edizione:** ancora aperto (API/Webhook non gated per edizione allo stato attuale).

---

## 0. Idea di fondo

Favilla è maturo lato *sistema* (auth+SSO, notifiche multicanale, backup, scheduler, health check, reports…). Manca **reach** (arrivare all'utente ovunque) e **apertura** (integrarsi con l'esterno). Le tre slice non sono alternative: sono un unico stack.

```
                    ┌─ Web Push  ──┐   nuovo channel driver → notifiche su telefono/desktop
   PWA installabile ┤              │
   (Slice 1)        └─ Service W.  │   app shell offline
                                   │
   API a token ─────────────────── ┼─ upgrada la PWA a client offline "vero"
   (Slice 2)                       │
                                   └─ Webhook in uscita → eventi verso URL esterni
   (Slice 3)                          (HMAC + retry via Scheduler)
```

**Perché si innestano bene (seam reali già presenti):**

| Serve | Seam esistente | File |
|---|---|---|
| Nuovo canale notifica | `NotificationChannelDriverInterface` + mappa driver | `app/Modules/Notifications/Services/NotificationChannelDriverInterface.php`, `…/NotificationQueueProcessorService.php` |
| Coda + retry + backoff | queue processor (`releaseForRetry`) | `…/NotificationQueueProcessorService.php` |
| Middleware su gruppo route | `$router->group(['middleware'=>[...]])` | ogni `routes.php` di modulo |
| Throttling per token | `RateLimiter` + `RateLimitMiddleware` + tabella `rate_limits` | `app/Security/RateLimiter.php`, `app/Middleware/RateLimitMiddleware.php` |
| Registry eventi | `notification_event_types`, `notification_event_channel_bindings` | modulo Notifications |
| Job periodici (retry webhook, cleanup token) | Scheduler (`php favilla scheduler:run`) | modulo Scheduler |
| Segreti/config | `app_settings` + `.env` (`EnvWriterService`) | modulo Admin |

**Convenzioni da rispettare** (da [`docs/contracts/data.md`](../contracts/data.md), [`security.md`](../contracts/security.md), [`i18n.md`](../contracts/i18n.md)):
schema in `database/schema.sql` + migrazioni idempotenti `app/Modules/<Module>/migrations/NNN_*.sql`; `utf8mb4_unicode_ci`; `created_at/updated_at`; `created_by INT UNSIGNED NULL REFERENCES users(id) ON DELETE SET NULL`; Repository estende `BaseRepository` con `$fillable`; prepared statements; copy via `t()` (IT canonica); permessi in `permissions.php` con `INSERT IGNORE`; route statiche prima delle parametriche, un permesso per azione.

---

## 1. Slice 1 — PWA installabile + Web Push

**Valore:** ogni utente riceve notifiche push su telefono/desktop senza Telegram, e può "installare" Favilla come app. Autonomo, non richiede l'API.

### 1.1 Web Push — il quarto channel driver

`WebPushChannelDriver` implementa l'interfaccia esistente (fotocopia strutturale di `TelegramChannelDriver`):

```php
final class WebPushChannelDriver implements NotificationChannelDriverInterface
{
    public function channel(): string { return 'webpush'; }

    public function send(array $job): array
    {
        $subs = $this->subRepo->activeForUser((int) $job['user_id']);
        if ($subs === []) {
            return ['status' => 'skipped', 'error_message' => 'Nessuna subscription push attiva.'];
        }
        // cifra payload (aes128gcm) + firma VAPID → POST a ogni endpoint
        // 404/410 dall'endpoint ⇒ subscription morta ⇒ soft-delete e non è un errore
        // return ['status' => 'sent'|'failed'|'skipped', 'provider_message_id' => null, 'error_message' => …]
    }
}
```

**Registrazione** (unico punto d'aggancio, dentro il modulo Notifications):
```php
// NotificationQueueProcessorService::__construct()
$this->drivers = [
    'email'    => app(EmailChannelDriver::class),
    'telegram' => app(TelegramChannelDriver::class),
    'webpush'  => app(WebPushChannelDriver::class),   // ← nuovo
];
```
Il canale entra così automaticamente in coda, retry, tracking delivery e preferenze utente per-evento (già esistenti).

**Cifratura/VAPID:** libreria `minishlink/web-push` (composer, MIT) — evita di implementare a mano ECDH + AES-GCM. Le chiavi VAPID (pubblica/privata) si generano una volta e si salvano in `app_settings` (o `.env`). → *decisione D1*.

### 1.2 Tabella nuova

```sql
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    endpoint      VARCHAR(1024) NOT NULL,
    p256dh        VARCHAR(255)  NOT NULL,
    auth          VARCHAR(255)  NOT NULL,
    user_agent    VARCHAR(255)  NULL,
    last_used_at  TIMESTAMP     NULL DEFAULT NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_endpoint (endpoint(191)),
    KEY idx_user (user_id),
    CONSTRAINT fk_push_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
`web_push` come nuova riga in `notification_channels` (seed) così compare nelle preferenze utente.

### 1.3 PWA (frontend)

- `public/manifest.webmanifest` — `name`, `short_name`, `icons` (192/512), `display: standalone`, `start_url`, `theme_color` (dal tema/palette utente).
- `public/sw.js` — service worker a **scope root** (deve stare in `public/`):
  - `install`/`activate`: precache dell'app shell (bootstrap, app.css/js, Font Awesome, htmx).
  - `fetch`: navigazioni → *network-first* con fallback a pagina `offline`; asset statici → *cache-first*. Le GET HTMX restano *network-only* in Slice 1 (l'offline "vero" arriva con l'API in Slice 2).
  - `push` → `showNotification(title, {body, icon, data.url})`; `notificationclick` → focus/apre `data.url`.
- Registrazione SW + `PushManager.subscribe({applicationServerKey: <VAPID pub>})` da un toggle in `notifications/settings` (accanto a Telegram). Il `POST /notifications/push/subscribe` salva la subscription.

### 1.4 Route nuove (dentro il gruppo `notifications`, auth+csrf)

```php
$r->post('/push/subscribe',   [PushController::class, 'subscribe'])->name('notifications.push.subscribe');
$r->post('/push/unsubscribe', [PushController::class, 'unsubscribe'])->name('notifications.push.unsubscribe');
$r->get('/push/vapid-key',    [PushController::class, 'vapidPublicKey'])->name('notifications.push.vapid');
```

### 1.5 Gotcha
- **iOS/iPadOS:** il Web Push funziona solo se la PWA è **installata** in home (Safari 16.4+). Su desktop e Android nessun vincolo.
- Il service worker richiede **HTTPS** (o `localhost`). In dev XAMPP va servito da `https` o testato su localhost.
- `start_url`/scope devono tenere conto del `base_path` (`/favilla/public`).

---

## 2. Slice 2 — API pubblica a token

**Valore:** sblocca un client mobile "vero" (offline/background sync) e abilita integrazioni esterne. Riusa Service/Repository esistenti: l'API è un *serializzatore JSON* sopra la logica che già c'è.

### 2.1 Autenticazione — Personal Access Token

Nuovo `ApiTokenMiddleware` in `app/Middleware/` (superficie consentita), speculare ad `AuthMiddleware` ma **stateless** e **JSON-first**:
- legge `Authorization: Bearer <token>`;
- risolve l'hash (`hash('sha256', $token)`) in `personal_access_tokens`, verifica scadenza/revoca;
- popola un contesto utente *senza* sessione PHP; su fallimento → `401 application/json` (non redirect);
- applica gli **scope** del token (vedi 2.4).

```sql
CREATE TABLE IF NOT EXISTS personal_access_tokens (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    name         VARCHAR(120) NOT NULL,          -- etichetta scelta dall'utente
    token_hash   CHAR(64)     NOT NULL,          -- sha256, il token in chiaro si mostra una volta sola
    scopes       JSON         NULL,              -- lista di permission slug
    last_used_at TIMESTAMP    NULL DEFAULT NULL,
    expires_at   TIMESTAMP    NULL DEFAULT NULL,
    revoked_at   TIMESTAMP    NULL DEFAULT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token_hash (token_hash),
    KEY idx_user (user_id),
    CONSTRAINT fk_pat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Routing e versioning

Gruppo dedicato, fuori dal middleware di sessione, con throttling per-token:
```php
$router->group([
    'prefix'     => 'api/v1',
    'middleware' => [ApiTokenMiddleware::class, ApiRateLimitMiddleware::class],
], function ($r) {
    $r->get('/me',            [Api\MeController::class, 'show']);
    $r->get('/tasks',         [Api\TasksApiController::class, 'index']);   // riusa TasksService
    $r->get('/tasks/{id}',    [Api\TasksApiController::class, 'show']);
    $r->post('/tasks',        [Api\TasksApiController::class, 'store']);
    // …roll-out per modulo, on-demand
});
```
- **Nessun CSRF** sull'API (è token-based, non cookie-based); il `CsrfMiddleware` resta solo sulle form web.
- Prefisso `api/v1` per poter evolvere senza rompere i client.

### 2.3 Envelope JSON coerente

```jsonc
// success
{ "data": { … }, "meta": { "page": 1, "per_page": 25, "total": 130 } }
// error
{ "error": { "code": "validation_failed", "message": "…", "details": { "field": ["…"] } } }
```
Un `ApiController` base (in `app/Support/` o `app/Core/`) centralizza envelope, paginazione (riusa `listPaginated()` dei repo), status code e mappa delle eccezioni.

### 2.4 Scope = permessi esistenti (riuso elegante)

Gli scope del token sono un **sottoinsieme dei permission slug già esistenti** (`tasks.view`, `contacts.create`, …). L'endpoint richiede il suo permesso; il gate effettivo è `min(permessi utente, scopi token)`. Nessun nuovo modello di autorizzazione da inventare.

### 2.5 Gestione token (UI)
Sezione nel profilo utente: crea token (nome, scadenza, scope selezionabili tra i *propri* permessi), token in chiaro mostrato **una sola volta**, lista/revoca. Permesso nuovo: `api.tokens.manage` (self-service) — → *decisione D3*.

### 2.6 Documentazione
Spec **OpenAPI 3.1** servita a `/api/v1/openapi.json` + una pagina docs statica. → *decisione D4* (generata a mano vs da attributi).

---

## 3. Slice 3 — Webhook in uscita

**Valore:** Favilla notifica sistemi esterni quando succede qualcosa (Zapier/n8n/endpoint custom). Poggia sul registry eventi delle notifiche: **ogni evento che già alimenta le notifiche può fan-out anche come webhook.**

### 3.1 Tabelle

```sql
CREATE TABLE IF NOT EXISTS webhook_endpoints (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,               -- proprietario (NULL = di sistema)
    url         VARCHAR(1024) NOT NULL,
    secret      VARCHAR(255)  NOT NULL,           -- per firma HMAC
    event_types JSON          NOT NULL,           -- slug da notification_event_types
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at  TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_active (is_active),
    CONSTRAINT fk_wh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    endpoint_id   INT UNSIGNED NOT NULL,
    event_type    VARCHAR(120) NOT NULL,
    payload       JSON         NOT NULL,
    status        ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    response_code SMALLINT     NULL,
    next_retry_at TIMESTAMP    NULL DEFAULT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dispatch (status, next_retry_at),
    CONSTRAINT fk_whd_endpoint FOREIGN KEY (endpoint_id) REFERENCES webhook_endpoints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Flusso
1. Un modulo emette un evento (già lo fa per le notifiche) → si accodano `webhook_deliveries` per gli endpoint sottoscritti.
2. Un **job Scheduler** (`webhooks:dispatch`) drena `pending`/retry con backoff esponenziale (riusa il pattern `releaseForRetry` della coda notifiche).
3. Consegna: `POST` JSON con header `X-Favilla-Event`, `X-Favilla-Delivery`, e `X-Favilla-Signature: sha256=<hmac(secret, body)>`. `2xx` = `sent`; altro = retry fino a N, poi `failed`.

### 3.3 Sicurezza
- **SSRF:** validare/whitelistare gli URL di destinazione (no IP privati/loopback/link-local) — endpoint definiti da utenti. → *decisione D5*.
- Firma HMAC obbligatoria; secret generato server-side, mai mostrato dopo la creazione.

---

## 4. Aspetti trasversali

| Tema | Nota |
|---|---|
| **Sicurezza** | Token/secret sempre **hashati** a riposo; in chiaro mostrati una sola volta. SSRF sui webhook. Rate limit su API *e* su subscribe push. Le `SecurityHeadersMiddleware` restano. |
| **i18n** | Ogni stringa UI via `t()`, IT canonica poi en/fr/de/es; `php favilla lang:check`. Header/payload API restano neutri (non localizzati). |
| **Permessi** | Nuovi: `api.tokens.manage`, `webhooks.manage`, `notifications.push` (opzionale). `INSERT IGNORE`; poi `context:generate` e logout/login. |
| **Editions** | API pubblica + webhook plausibilmente gated su edizione Team/Developer; Web Push/PWA in tutte. → *decisione D6*. |
| **Testing** | Driver Web Push: unit con HTTP mockato (come `TelegramChannelDriverTest`). API: harness HTTP controller già esistente. Webhook: unit su firma + retry, integrazione su dispatch. Regola dialetto SQLite/MariaDB nei test. |
| **Config** | Chiavi VAPID e default API (rate, scadenza token) in Admin → Impostazioni (`app_settings`), scrivibili via `EnvWriterService`. |
| **context:generate** | Dopo route/permessi/schema nuovi, rigenerare `project_context.json` + `context/<Module>.json`. |

---

## 5. Decisioni aperte (da chiudere insieme)

| # | Decisione | Opzioni | Default proposto |
|---|---|---|---|
| **D1** | Chiavi VAPID | `.env` vs `app_settings` (gen. da UI) | `app_settings` + generazione guidata in Admin |
| **D2** | Dipendenza Web Push | `minishlink/web-push` vs implementazione propria | libreria (MIT, evita crypto a mano) |
| **D3** | Modello auth API | Personal Access Token (per-utente) vs OAuth2 client-credentials (per-app) | PAT ora; OAuth2 dopo se serve M2M |
| **D4** | Docs API | OpenAPI a mano vs generata da attributi PHP | a mano su `v1`, piccola |
| **D5** | Anti-SSRF webhook | blocklist IP privati vs allowlist domini | blocklist IP privati/loopback |
| **D6** | Gating per edizione | API+webhook solo Team/Dev? | sì; PWA/push in tutte |
| **D7** | Collocazione codice API | nuovo modulo `Api` vs controller `Api\` dentro ogni modulo | controller `Api\` per-modulo (riusa i Service in loco) |

---

## 6. Sequenza consigliata & sforzo (indicativo)

1. **Slice 1 — Web Push + PWA shell** · *rischio basso, valore immediato per tutti.* Seam più pulito (4° driver). ~1 area frontend + 1 driver + 1 tabella.
2. **Slice 2 — Core API a token** · *fondamenta.* Middleware + envelope + 2-3 endpoint pilota (es. Tasks, Contacts), poi roll-out per-modulo on-demand.
3. **Slice 3 — Webhook** · *ecosistema.* Poggia sul registry eventi + Scheduler; naturale dopo l'API.

> Ogni slice è rilasciabile da sola. L'ordine massimizza "valore percepito subito" → "fondamenta" → "apertura".
