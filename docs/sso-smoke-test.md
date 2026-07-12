# SSO (OIDC) — smoke test contro un IdP reale

Runbook per la verifica pre-release dell'SSO OIDC richiesta da
[security.md §6](contracts/security.md): ogni riga della checklist contrattuale
mappata su passi concreti contro un **Keycloak reale** (lab usa-e-getta in
[`tools/sso-lab/`](../tools/sso-lab/docker-compose.yml)).

Va eseguito **una volta prima di promuovere/annunciare la feature SSO** e
ripetuto solo se cambia `OidcService`/`ExternalIdentityService` o il flusso di
login. Esito atteso: tutte le righe della matrice PASS.

## 1. Prerequisiti

- Docker (per il lab Keycloak). L'istanza Favilla può girare ovunque:
  XAMPP locale, `docker compose`, VPS demo.
- Un utente Favilla admin per configurare Admin → Configurazione → SSO.
- Se Favilla gira su **XAMPP/Windows**: verifica `curl.cainfo` in `php.ini`
  (contratto §6) — con l'issuer HTTP del lab non serve, ma con un IdP HTTPS
  reale senza CA bundle le chiamate discovery/token falliscono.

## 2. Avvio del lab

```bash
cd tools/sso-lab
docker compose up -d
# pronto quando risponde:
curl -s http://localhost:8081/realms/favilla-lab/.well-known/openid-configuration | head -c 200
```

Configura Favilla (Admin → Configurazione → SSO):

| Campo | Valore |
|---|---|
| Issuer | `http://localhost:8081/realms/favilla-lab` (vedi nota network nel compose se Favilla è in Docker) |
| Client ID | `favilla` |
| Client Secret | `favilla-lab-secret` |
| Scope | `openid profile email` (default) |
| JIT | OFF (si accende solo nei passi 3.3/3.4) |
| Solo SSO | OFF (si accende solo nel passo 3.7) |

Utenti del realm (password = username):

| Utente IdP | Proprietà | Serve per |
|---|---|---|
| `lucamarinelli` | e-mail verificata = utente demo Favilla | email-match su utente esistente |
| `alice` | e-mail verificata, NON esiste in Favilla | JIT on/off |
| `bob-unverified` | e-mail NON verificata | requisito `email_verified === true` |
| `dave-disabled` | disabilitato lato IdP | blocco a monte (Keycloak) |

> Se l'istanza Favilla non ha il dataset demo, crea a mano un utente con
> e-mail `lucamarinelli@favilla.test` per il passo 3.1.

## 3. Matrice di test

Annota PASS/FAIL nella colonna esito. "Logout" tra un passo e l'altro.

| # | Caso (contratto §6) | Passi | Atteso | Esito |
|---|---|---|---|---|
| 3.1 | Happy path, email-match | Login Favilla → pulsante SSO → credenziali `lucamarinelli` su Keycloak | Rientro in Favilla autenticato come l'utente esistente; audit `sso_login`; riga in `oidc_identities` | |
| 3.2 | Interstitial (cookie Strict) | Durante il 3.1, osserva il rientro dal callback (DevTools → Network) | `/auth/oidc/callback` risponde **200 con pagina interstitial** same-origin, MAI 302; la sessione nasce dopo la navigazione client-side | |
| 3.3 | JIT OFF, utente ignoto | JIT OFF → login SSO con `alice` | Rifiutato con errore generico; audit `sso_login_failed`; NESSUN utente creato | |
| 3.4 | JIT ON, provisioning | JIT ON → login SSO con `alice` | Utente creato con ruolo di default (MAI admin); audit `sso_user_provisioned`; secondo login riusa l'identità | |
| 3.5 | E-mail non verificata | Login SSO con `bob-unverified` (JIT ON) | Rifiutato: `email_verified === true` è requisito sia per match sia per JIT | |
| 3.6 | Utente Favilla disattivato | Disattiva l'utente di `alice` in Favilla (Admin → Utenti) → login SSO con `alice` | Rifiutato a OGNI login SSO (`is_active` enforced); riattivalo a fine test | |
| 3.7 | Solo-SSO + break-glass | Attiva "Solo SSO" → visita `/login` → poi `/login?local=1` | `/login` nasconde il form password; `?local=1` lo mostra (break-glass); il POST mantiene rate-limit/TOTP/policy | |
| 3.8 | Rotazione chiavi realm | Keycloak Admin → Realm settings → Keys → aggiungi un nuovo provider RSA con priorità più alta (o rigenera) → login SSO | Login OK senza riavvii: la verifica firma rifà il fetch JWKS (cache-busting) sulla nuova chiave | |
| 3.9 | Utente disabilitato lato IdP | Login SSO con `dave-disabled` | Keycloak blocca a monte; Favilla non riceve il codice; nessuna sessione | |
| 3.10 | Alg whitelist / issuer | (statico, già coperto da unit test) verifica in audit/log che i fallimenti riportino reason senza dettagli protocollo nella UI | Errori UI generici; dettaglio solo nei log | |

## 4. Pulizia

```bash
cd tools/sso-lab && docker compose down -v
```

In Favilla: disattiva SSO (o ripristina la config reale), riattiva l'eventuale
utente disattivato al 3.6, elimina l'utente JIT `alice` se creato.

## 5. Registro esiti

| Data | Versione Favilla | IdP | Esito | Note |
|---|---|---|---|---|
| _(da compilare)_ | | Keycloak 26 (lab) | | |
