# Security — platform contract

> On-demand contract. Entry point and map: [`CLAUDE.md`](../../CLAUDE.md).
> Tags: `MUST` non-negotiable · `SHOULD` default unless justified · `NOTE` practical reference.

## 1. Application security invariants
| Invariant | Contract |
|---|---|
| Client IP | use `App\Support\ClientIp::resolve()`; never raw `REMOTE_ADDR` |
| Post-login redirect | `redirect_to` must start with `/` and contain no `//`, `\\`, `..` after decode |
| DB session | every 60s check `hash('sha256', session_id())` against `sessions.token_hash` with `hash_equals()` |
| CSRF & `_method` | the POST → PUT/DELETE override must not bypass `CsrfMiddleware` |
| Impersonation cookie | `setcookie` with array options, `samesite=Strict`, `secure` only in prod+HTTPS, `httponly=false` is intentional |
| Mail templates | `{{key}}` is escaped; `{{!key}}` only for server-approved HTML |
| CSV export | RFC 5987 filename; prefix `'` on cells starting with `= + - @ \t \r` (formula injection) |
| Layout names | only names matching `/^[a-zA-Z0-9_\-]+$/` |

## 2. Non-negotiable UI/data rules
- `MUST`: every untrusted output goes through `e()`.
- `MUST`: every mutating form includes `csrf_field()`.
- `MUST`: every SQL query uses prepared statements.
- `MUST`: every dynamic `ORDER BY` uses a whitelist (`in_array(..., true)`).
- `MUST`: inline scripts carry `nonce="<?= e(csp_nonce()) ?>"`; permission checks in views use `has_permission('slug')`.

## 3. Input sanitization
- `MUST`: at controller boundaries use `cleanPost()` / `cleanGet()`.
- `MUST`: never sanitize passwords, free HTML, files or binaries with these helpers.
- `SHOULD`: keep a private `readFormData()` to normalize form input.
- `NOTE`: `App\Security\Sanitizer` methods — `clean()`, `email()`, `int()`, `html()`, `color()`.

## 4. Flash & session (error handling)
| Key | Contract |
|---|---|
| `_flash_success` / `_flash_error` | post-redirect message, or structured feedback payload |
| `_errors` | form errors as `['field' => ['msg']]` |
| `_old` | previous values to repopulate |

- `MUST`: on form error set `_errors` + `_old`, then redirect to the form; on not-found set an error flash, redirect, and `return`.
- `MUST`: flash values are **text-first** — no HTML/markup/pre-escaped entities in session.
- `SHOULD`: use a structured payload when you need `title`, `type`, `channel`, `duration`, `persistent`, `source`, `actions`; clear `_errors`/`_old` after rendering the form.
- → Field-level error rendering and the HTMX `422` contract: [`ui.md`](ui.md).

## 5. Soft-delete policy
- `SHOULD`: application entities use `deleted_at` + Repository `$softDelete = true`.
- `NOTE`: technical/compliance tables documented as exceptions keep hard delete by design.
- → SQL conventions and Repository safety: [`data.md`](data.md).

## 6. SSO (OIDC)
- Flow: authorization code + **PKCE S256**; state/nonce/verifier travel in the
  encrypted single-use cookie `favilla_oidc_txn` (SameSite=**Lax**, TTL 600s) —
  NOT in the session: the session cookie is SameSite=**Strict** and is not sent
  on the cross-site navigation back from the IdP. For the same reason the
  callback never answers 302: it renders a same-origin interstitial that
  navigates client-side (`Auth/Views/oidc-interstitial.php`).
- ID token validation (`OidcService::validateIdToken`): alg whitelist
  {RS256, ES256} checked BEFORE decoding, JWKS signature with one cache-busting
  refetch on key rotation, `iss` ≡ configured ≡ discovery, `aud`/`azp`,
  `nonce` (hash_equals), `exp` with 60s leeway, non-empty `sub`.
- Session establishment reuses `AuthService::loginExternal()` (same
  `createSession` as the password path: regenerated session id, CSRF, DB
  session record, session limiter, `UserLoggedIn` event). **No local TOTP**:
  MFA is delegated to the IdP.
- Provisioning (`ExternalIdentityService::resolveUser`): sub match → verified
  e-mail match (case-insensitive, `email_verified === true` required) → JIT if
  enabled (default role never `admin`). `is_active`/`deleted_at` enforced on
  EVERY SSO login. Client secret stored AES-256-GCM encrypted.
- "SSO only" hides the password form; `/login?local=1` is a **break-glass**
  (visibility only — POST /login keeps rate limiting, TOTP and password policy).
- Audit actions: `sso_login`, `sso_login_failed` (+reason), `sso_identity_linked`,
  `sso_user_provisioned`. UI errors are generic (`auth.errors.sso_*`); protocol
  detail goes to logs only.
- `MUST` (ops): on Windows/XAMPP set `curl.cainfo` in php.ini or discovery/token
  HTTPS calls fail TLS verification. Pre-release smoke test against a real IdP
  (Keycloak/Authentik in Docker): happy path, SSO-only + break-glass, JIT
  on/off, deactivated user, realm key rotation, Strict-cookie interstitial.
