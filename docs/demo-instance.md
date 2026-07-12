# Istanza demo pubblica

Come pubblicare una demo di Favilla su un VPS: dataset "Aurora Studio"
precaricato, reset automatico ogni ora, avviso con le credenziali sulla pagina
di login. È l'asset per il lancio (Show HN) ed è anche il posto naturale dove
eseguire lo [smoke test SSO](sso-smoke-test.md) contro un IdP reale.

> **Mai** usare questa configurazione su dati reali: `DEMO_MODE=true` sblocca
> un comando che cancella tutto il database e gli upload a ogni ciclo, e il
> dataset demo contiene utenti con password deboli (password = username).

## 1. Requisiti

- VPS con Docker + Docker Compose (1 vCPU / 1–2 GB RAM bastano).
- Un dominio o sottodominio (es. `demo.example.org`) puntato al VPS.
- Un reverse proxy con TLS davanti allo stack (Caddy, Traefik o nginx +
  certbot): lo stack espone HTTP su `APP_PORT` (default 8080).

## 2. Avvio

```bash
curl -LO https://raw.githubusercontent.com/Myth70/favilla/main/quickstart.sh
bash quickstart.sh --demo          # genera .env (chiavi comprese) e primo avvio

# poi passa allo stack demo permanente (banner + reset orario):
docker compose -f docker-compose.yml -f docker-compose.demo.yml up -d
```

In `.env` imposta `APP_URL` all'URL pubblico (es. `https://demo.example.org`)
prima dell'avvio: serve a link assoluti, cookie e Web Push.

Cosa fa l'overlay [`docker-compose.demo.yml`](../docker-compose.demo.yml):

| Pezzo | Effetto |
|---|---|
| `AUTO_MIGRATE` + `DEMO_DATA` | primo avvio hands-off su DB vuoto: schema + seed + dataset demo |
| `DEMO_MODE=true` | avviso demo con credenziali sul login; sblocca `demo:reset` |
| servizio `demo-reset` | ogni `DEMO_RESET_INTERVAL` secondi (default 3600) svuota gli upload, ricrea il DB (`migrate --fresh`) e ricarica il dataset |

Credenziali mostrate ai visitatori: `lucamarinelli` / `lucamarinelli`
(utente manager del dataset; l'elenco completo è in
`database/seeds/test_users.sql`). L'utente admin creato dal setup NON va
comunicato.

## 3. Reset manuale

```bash
docker compose -f docker-compose.yml -f docker-compose.demo.yml \
  exec -T app php favilla demo:reset
```

Il comando si rifiuta di girare senza `DEMO_MODE=true` nell'ambiente: è la
guardia che protegge le installazioni reali.

## 4. Hardening consigliato

- **Mail**: lascia il driver mail non configurato (default) — la demo non deve
  spedire nulla; i visitatori possono inserire indirizzi altrui.
- **Webhook/API**: sono funzioni della demo, ma il reset orario revoca token e
  endpoint registrati: nessuna azione necessaria.
- **robots.txt / noindex**: valuta di escludere la demo dai motori di ricerca
  (direttiva nel reverse proxy o `X-Robots-Tag: noindex`).
- **Rate limiting**: il reverse proxy dovrebbe limitare le richieste per IP; il
  rate limiting applicativo su login/API è già attivo.
- **Isolamento**: il VPS della demo non deve ospitare altri servizi con dati
  reali (le credenziali demo sono pubbliche per definizione).

## 5. Smoke test SSO sulla demo

Il VPS demo ha Docker: è il posto giusto per eseguire una tantum lo
[smoke test SSO](sso-smoke-test.md) con il lab
[`tools/sso-lab/`](../tools/sso-lab/docker-compose.yml). Nota: il reset orario
cancella la configurazione SSO — esegui la checklist entro un ciclo di reset o
ferma temporaneamente il servizio `demo-reset`:

```bash
docker compose -f docker-compose.yml -f docker-compose.demo.yml stop demo-reset
# ... smoke test ...
docker compose -f docker-compose.yml -f docker-compose.demo.yml start demo-reset
```
