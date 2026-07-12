# Favilla — API, Webhooks & Web Push reference

> Developer reference for the **Reach & Integrations** stack: the public REST API,
> outgoing webhooks, and Web Push. English-only by design (integrators read
> English; the multilingual surface is the in-app Help Online).
>
> **Source of truth for the endpoint catalog is [`openapi.json`](openapi.json)**
> (OpenAPI 3.1). This document is the narrative guide — auth, envelopes, signature
> verification, examples — and links to the spec rather than duplicating field
> lists. When the two disagree, the spec wins.

## Contents

1. [REST API v1](#1-rest-api-v1)
   - [Base URL & versioning](#11-base-url--versioning)
   - [Authentication (Personal Access Tokens)](#12-authentication-personal-access-tokens)
   - [Response envelope](#13-response-envelope)
   - [Error codes](#14-error-codes)
   - [Rate limiting](#15-rate-limiting)
   - [Pagination](#16-pagination)
   - [Endpoints](#17-endpoints)
2. [Outgoing Webhooks](#2-outgoing-webhooks)
   - [Setup](#21-setup)
   - [The delivery request](#22-the-delivery-request)
   - [Verifying the signature](#23-verifying-the-signature)
   - [Retries & delivery status](#24-retries--delivery-status)
   - [Security](#25-security)
3. [Web Push (PWA)](#3-web-push-pwa)
4. [Security summary](#4-security-summary)

---

## 1. REST API v1

The REST API exposes module data as JSON. It is a thin serializer over the same
services the web UI uses, so API behaviour matches the app (ownership, sharing,
validation).

### 1.1 Base URL & versioning

All endpoints live under the `api/v1` prefix, **relative to the installation base
path**:

- XAMPP / sub-directory install: `https://host/favilla/public/api/v1`
- Docker / root install: `https://host/api/v1`

Examples below use `{base}` for that prefix. The version is in the path so `v1`
can evolve without breaking clients.

### 1.2 Authentication (Personal Access Tokens)

The API is **stateless and token-based** — no cookies, no CSRF. Every request
authenticates with a Personal Access Token (PAT) in the `Authorization` header:

```
Authorization: Bearer favilla_pat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

**Creating a token** (web UI, not an API call): **Profile → API tokens**. You choose:

- a **name** (label);
- an **expiry** (30 / 90 / 365 days, or never);
- one or more **scopes** — *scopes are mandatory*. A scope is a permission slug
  (`tasks.view`, `contacts.view`, …) and can only be a **subset of your own
  permissions**. A token can never do more than the user who created it.

The plaintext token (`favilla_pat_` + 40 hex chars) is shown **once** — copy it
immediately. At rest only its SHA-256 hash is stored. Revoke a token from the same
page; access stops on the next request.

The effective authorization for any request is `min(user permissions, token scopes)`.

### 1.3 Response envelope

**Success** — a `data` payload, plus `meta` for collections:

```jsonc
{
  "data": { "id": 1, "title": "Buy milk", "status": "todo" },
  "meta": { "page": 1, "per_page": 15, "total": 42 }   // collections only
}
```

**Error** — a stable `error` object:

```jsonc
{
  "error": {
    "code": "validation_failed",
    "message": "Validation failed.",
    "details": { "title": ["required"] }   // optional
  }
}
```

Error messages are neutral English and are **not** localized (they target
integrators). This envelope is returned for *every* failure, including 404 on an
unknown path and 500 on an unexpected error.

### 1.4 Error codes

| HTTP | `code`                    | Meaning                                             |
|-----:|---------------------------|-----------------------------------------------------|
| 401  | `unauthenticated`         | Missing `Authorization: Bearer` header              |
| 401  | `invalid_token`           | Token unknown, expired or revoked                   |
| 401  | `inactive_account`        | The token's user is not active                      |
| 403  | `forbidden`               | Permission or token scope insufficient              |
| 404  | `not_found`               | Resource does not exist (or not visible to you)     |
| 405  | `method_not_allowed`      | Wrong HTTP method for the path                       |
| 422  | `validation_failed`       | Invalid input (see `details`)                       |
| 429  | `rate_limited`            | Per-token rate limit exceeded (see headers)         |
| 500  | `server_error`            | Unexpected server error                             |
| 503  | `api_disabled`            | The public API is turned off in Admin settings      |

401 responses also carry `WWW-Authenticate: Bearer`.

### 1.5 Rate limiting

Requests are throttled **per token** over a sliding 60-second window (default
**120 req/min**, configurable in Admin → settings as `api_rate_limit_per_minute`).
Every response includes:

```
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 118
X-RateLimit-Reset: 1735689600      # unix time when the window frees capacity
```

On `429` the body's `error.retry_after` and the `Retry-After` header tell you how
many seconds to wait.

### 1.6 Pagination

Collection endpoints accept `?page=N` (1-based) and return `meta.page`,
`meta.per_page`, `meta.total`. Page size is fixed per resource.

### 1.7 Endpoints

The full catalog with request/response schemas is in
[`openapi.json`](openapi.json) (also served live at `{base}/openapi.json`,
unauthenticated). Current surface:

| Method & path                | Scope required   | Notes                          |
|------------------------------|------------------|--------------------------------|
| `GET  {base}/me`             | any              | Identity + token scopes        |
| `GET  {base}/tasks`          | `tasks.view`     | Paginated; `q`, `status`, `sort`, `dir` filters |
| `POST {base}/tasks`          | `tasks.create`   | `title` required; `status`/`priority` validated |
| `GET  {base}/tasks/{id}`     | `tasks.view`     |                                |
| `PUT  {base}/tasks/{id}`     | `tasks.edit`     |                                |
| `DELETE {base}/tasks/{id}`   | `tasks.delete`   |                                |
| `GET  {base}/contacts`       | `contacts.view`  | Role-based sharing applies     |
| `POST {base}/contacts`       | `contacts.create`| `nome` required; avatar not handled via API |
| `GET  {base}/contacts/{id}`  | `contacts.view`  |                                |
| `PUT  {base}/contacts/{id}`  | `contacts.edit`  | Partial update; owned contacts only |
| `DELETE {base}/contacts/{id}`| `contacts.delete`| Owned contacts only            |
| `GET  {base}/calendar/events`| `calendar.view`  | `from`/`to` range (default 30 days, max 400); recurrences expanded |
| `GET  {base}/calendar/events/{id}` | `calendar.view` | Master events only (not virtual occurrences) |
| `GET  {base}/projects`       | `progetti.view`  | Owner/member scope; `progetti.view_all` sees everything. 404 if the module is disabled |
| `GET  {base}/projects/{id}`  | `progetti.view`  |                                |
| `GET  {base}/documents`      | `documenti.view` | Metadata only (no binary download); UI visibility rules. 404 if the module is disabled |
| `GET  {base}/documents/{id}` | `documenti.view` |                                |
| `GET  {base}/openapi.json`   | none (public)    | The spec itself                |

**Examples**

```bash
# Identity
curl -s -H "Authorization: Bearer $TOKEN" "{base}/me"

# List tasks (page 2, filter by status)
curl -s -H "Authorization: Bearer $TOKEN" "{base}/tasks?page=2&status=todo"

# Create a task (JSON body required)
curl -s -X POST "{base}/tasks" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Ship the release","priority":"high","due_date":"2026-08-01"}'
```

`PUT`/`PATCH` must send a JSON body (`Content-Type: application/json`); a
form-urlencoded body on those methods is not accepted.

---

## 2. Outgoing Webhooks

Webhooks push an HTTP `POST` to an external URL whenever a subscribed event fires
in Favilla — the same event registry that drives notifications. Use them with
Zapier, n8n, or any custom endpoint.

### 2.1 Setup

In **Webhooks** (sidebar, permission `webhooks.view` / manage with
`webhooks.manage`):

1. Create an **endpoint**: destination **URL (HTTPS only)** + the **events** to
   subscribe to.
2. You receive a **signing secret**, shown **once** — store it in your receiver.
3. Optionally send a **test** delivery and inspect the **delivery log**.

### 2.2 The delivery request

Each delivery is a JSON `POST` with these headers:

| Header                 | Example                              | Purpose                          |
|------------------------|--------------------------------------|----------------------------------|
| `Content-Type`         | `application/json`                   |                                  |
| `X-Favilla-Event`      | `tasks.task_overdue`                 | Event slug                       |
| `X-Favilla-Delivery`   | `12345`                              | Unique delivery id (idempotency) |
| `X-Favilla-Timestamp`  | `1735689600`                         | Unix time the request was signed |
| `X-Favilla-Signature`  | `t=1735689600,v1=9f86d081…`          | HMAC signature (see below)       |

The body shape:

```jsonc
{
  "event": "tasks.task_overdue",
  "module": "Tasks",
  "occurred_at": "2026-07-10T18:30:00+02:00",
  "title": "Task overdue",
  "body": "…",
  "link": "https://…",           // may be null
  "context": { "task_id": 42 }   // event-specific
}
```

### 2.3 Verifying the signature

The signature protects **origin, integrity and freshness**. `X-Favilla-Signature`
is `t=<unix>,v1=<hex>`, where `<hex>` is:

```
HMAC_SHA256( secret, "{timestamp}.{raw_request_body}" )
```

The `timestamp` is bound into the HMAC (not just the body), so a captured request
cannot be replayed with a new timestamp. Your receiver must:

1. read `t` from the header (or the `X-Favilla-Timestamp` header — they match);
2. reject if `|now - t|` exceeds your tolerance (e.g. 5 minutes);
3. recompute the HMAC over `"{t}.{body}"` and compare with `v1` in
   **constant time**.

> Use the **raw** request body for the HMAC — do not re-serialize the parsed JSON,
> or whitespace/key-order differences will break the check.

**PHP**

```php
function verify(string $rawBody, string $sigHeader, string $secret, int $tolerance = 300): bool {
    $t = null; $v1 = null;
    foreach (explode(',', $sigHeader) as $part) {
        [$k, $val] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't' && ctype_digit($val)) $t = (int) $val;
        if ($k === 'v1') $v1 = $val;
    }
    if ($t === null || !$v1 || abs(time() - $t) > $tolerance) return false;
    $expected = hash_hmac('sha256', $t . '.' . $rawBody, $secret);
    return hash_equals($expected, $v1);
}
```

**Node.js**

```js
const crypto = require('crypto');

function verify(rawBody, sigHeader, secret, tolerance = 300) {
  const parts = Object.fromEntries(
    sigHeader.split(',').map((p) => p.trim().split('=').map((s) => s))
  );
  const t = Number(parts.t);
  if (!Number.isInteger(t) || !parts.v1) return false;
  if (Math.abs(Date.now() / 1000 - t) > tolerance) return false;
  const expected = crypto
    .createHmac('sha256', secret)
    .update(`${t}.${rawBody}`)
    .digest('hex');
  const a = Buffer.from(expected);
  const b = Buffer.from(parts.v1);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}
```

**Python**

```python
import hashlib, hmac, time

def verify(raw_body: bytes, sig_header: str, secret: str, tolerance: int = 300) -> bool:
    parts = dict(p.strip().split("=", 1) for p in sig_header.split(",") if "=" in p)
    try:
        t = int(parts.get("t", ""))
    except ValueError:
        return False
    v1 = parts.get("v1", "")
    if not v1 or abs(time.time() - t) > tolerance:
        return False
    expected = hmac.new(secret.encode(), f"{t}.".encode() + raw_body, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, v1)
```

### 2.4 Retries & delivery status

A delivery is `sent` on any `2xx`. Otherwise it is retried with **exponential
backoff** (5 → 15 → 45 → 135 → 135 minutes) up to **5 attempts**, then marked
`failed`. The dispatcher runs every few minutes via the scheduler
(`webhooks:dispatch`); it claims deliveries atomically so overlapping runs never
double-send. Make your receiver **idempotent** on `X-Favilla-Delivery`.

### 2.5 Security

- **HTTPS only.** URLs resolving to private, loopback, link-local, CGNAT,
  cloud-metadata (`169.254.169.254`), NAT64 or other reserved ranges are
  **blocked (anti-SSRF)** — checked at save time and again at each send, with the
  vetted IP **pinned** for the actual connection (no DNS-rebinding window).
- **Redirects are not followed** — a `3xx` toward an internal host becomes a
  failed delivery, never a followed request.
- **Rotate the secret** from the endpoint detail if you suspect exposure; the old
  secret stops validating immediately.

---

## 3. Web Push (PWA)

Web Push is the fourth notification channel (alongside in-app, email, Telegram).
It is **not an integration API** — there are no external HTTP endpoints — but the
mechanics matter to developers and operators.

**How it works**

- The admin generates a **VAPID** key pair in **Admin → Notifications → Web Push**
  (RFC 8292). The public key identifies the installation to push services; the
  private key signs each send and never leaves the server.
- A user enables push from **Notification settings**: the browser registers the
  service worker (`public/sw.js`) and subscribes with the VAPID public key. The
  subscription is stored server-side (`push_subscriptions`).
- When a notification event targets a user with the `web_push` channel enabled,
  the payload is encrypted (aes128gcm, RFC 8291) and delivered to each of the
  user's push endpoints.

**Payload the service worker receives**

```jsonc
{ "title": "…", "body": "…", "url": "/path/to/open", "tag": "favilla-<delivery_id>" }
```

**Subscription lifecycle**

- A `404`/`410` from a push service means the subscription is dead — it is pruned
  automatically.
- **Rotating the VAPID keys invalidates every existing subscription** (they were
  created with the old public key); the server clears them and clients
  re-subscribe on next visit. Only rotate keys when necessary.

**Platform notes**

- Requires **HTTPS** (or `localhost` in dev).
- On iOS/iPadOS, Web Push works only when the PWA is **installed to the Home
  Screen** (Safari 16.4+). Desktop and Android have no such restriction.

The app is installable as a PWA via `public/manifest.webmanifest`; offline
navigation falls back to `public/offline.html`.

---

## 4. Security summary

- **Secrets are shown once** (API tokens, webhook signing secrets) and never
  displayed again; API tokens are stored hashed. Webhook secrets and the VAPID
  private key are stored at rest with the same posture as other server secrets
  (e.g. Telegram bot tokens) and are excluded from audit logs.
- **The API carries no cookies**, so it is structurally immune to CSRF; the web
  token-management UI uses standard session auth + CSRF.
- **SSRF defense** on webhook destinations: reserved-range blocking, DNS
  resolution with IP pinning, and no redirect following.
- **Rate limiting** applies per token on the API.

See also `docs/contracts/integrations.md` (how these hook into the module system)
and `docs/contracts/security.md` (platform security invariants).
