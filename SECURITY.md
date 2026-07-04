# Security Policy

## Supported versions

Favilla is pre-1.0. Security fixes land on the `main` branch and the latest
tagged release. Until 1.0, only the most recent release receives security
updates.

| Version | Supported |
| ------- | --------- |
| latest release / `main` | ✅ |
| older pre-releases | ❌ |

## Reporting a vulnerability

**Please do not open a public issue for security vulnerabilities.**

Report privately through GitHub's [Security Advisories](https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing-information-about-vulnerabilities/privately-reporting-a-security-vulnerability)
("Report a vulnerability" in the **Security** tab), or by email to the address
listed on the maintainer's GitHub profile.

Please include:

- a description of the issue and its impact,
- steps to reproduce (a proof of concept if possible),
- affected version / commit.

We aim to acknowledge a report within **7 days** and to ship a fix or a clear
mitigation plan within **90 days**, coordinating disclosure with you.

## Security model

Favilla is a self-hosted application built on a custom PHP micro-framework. The
platform enforces a set of non-negotiable security invariants (see
[`docs/contracts/security.md`](docs/contracts/security.md)):

- **CSRF** — every state-changing request (`POST`/`PUT`/`DELETE`) is verified by
  `CsrfMiddleware` against the real HTTP method, so the `_method` override
  cannot bypass it. Every mutating form ships a CSRF token.
- **Output encoding** — untrusted output is escaped with `e()`; a per-request
  CSP nonce gates inline scripts (`script-src` has no `'unsafe-inline'`).
- **SQL** — prepared statements only; user-driven `ORDER BY` is whitelisted.
  Identifiers are never built from request input.
- **Uploads** — MIME is validated from magic bytes (the browser-supplied type
  is ignored), stored filenames are randomized, and the upload directories
  disable direct access and PHP execution. *Note:* this last protection is
  enforced via Apache `.htaccess`; non-Apache deployments must reproduce it at
  the web-server level.
- **Sessions** — the session id is regenerated on login (anti-fixation) and the
  DB-backed session token is re-checked with `hash_equals()`.
- **Transport** — `SecurityHeadersMiddleware` sets `X-Frame-Options: DENY`,
  `X-Content-Type-Options: nosniff`, a `Referrer-Policy`, CSP, and HSTS (only
  over real HTTPS, never from spoofable proxy headers).
- **2FA** — optional TOTP with anti-replay.

## Hardening checklist for operators

- Set a strong, unique `APP_KEY` and `BACKUP_ENCRYPTION_KEY` in `.env`.
- Run with `APP_ENV=production` and `APP_DEBUG=false`.
- Serve only `public/` as the web root; keep the rest of the tree non-public.
- Terminate TLS in front of the app and enable HSTS.
- Change the default administrator password on first login.
- Keep `composer install --no-dev` dependencies up to date (`composer audit`).
