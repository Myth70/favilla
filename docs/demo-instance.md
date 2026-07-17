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

## 2b. Installazione nativa (senza Docker)

Il tooling demo è PHP puro: funziona anche su un LAMP classico già a posto
(Apache con DocumentRoot su `public/`, TLS, redirect HTTP→HTTPS, dotfile
negati, PHP 8.2+). Dalla root del progetto sul server:

```bash
# 1. Porta il codice a una versione che include il tooling demo
#    (>= 2.3.0, o il branch feature/a3-sso-demo finché non è rilasciato):
git fetch origin && git checkout <ref>
composer install --no-dev
php database/migrate.php

# 2. In .env:
#    APP_URL=https://demo.example.org
#    DEMO_MODE=true

# 3. Primo caricamento del dataset (equivale a un reset completo):
php favilla demo:reset

# 4. Cron dell'utente che possiede i file (es. www-data):
#    * * * * *  cd /percorso/favilla && php favilla scheduler:run >> storage/logs/cron-scheduler.log 2>&1
#    0 * * * *  cd /percorso/favilla && php favilla demo:reset    >> storage/logs/demo-reset.log    2>&1
```

Il cron dello scheduler serve comunque (reminder, coda notifiche, retention);
quello orario è l'equivalente del sidecar `demo-reset` dello stack Docker.
Per il noindex senza toccare il repo, aggiungi nel vhost Apache:

```apache
Header always set X-Robots-Tag "noindex, nofollow"
```

## 3. Reset manuale

Stack Docker:

```bash
docker compose -f docker-compose.yml -f docker-compose.demo.yml \
  exec -T app php favilla demo:reset
```

Installazione nativa: `php favilla demo:reset` dalla root del progetto.

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
