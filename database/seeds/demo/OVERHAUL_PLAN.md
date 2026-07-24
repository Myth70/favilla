# Piano definitivo — Overhaul seed demo "Aurora Studio"

> Stato: approvato (decisioni raccolte 2026-07-24). Ambizione: **Pieno**.
> Deliverable a carico utente: **10 foto avatar** (vedi §2.4).

## 0. Principi & invarianti (da preservare)

- **ID fissi da 9001** + `INSERT IGNORE` ovunque → idempotenza, zero collisioni con dati reali.
- Guard `app_settings.demo_data_loaded` blocca il ricarico salvo `--force`.
- `SET NAMES utf8mb4;` in testa a ogni file SQL.
- **Solo MariaDB**: i seed demo NON girano sotto SQLite (i test default). Validati da
  `tests/Integration/DemoSeederIntegrationTest.php` (opt-in `RUN_DB_INTEGRATION=1`).
  → Le idiome date-dinamiche MariaDB sono ammesse.
- Layering/security non toccati: i seed sono SQL grezzo ma restano coerenti con ruoli/permessi.

## 1. Motore date (spec concreta)

Due regimi, usati insieme:

- **Copertura anno solare (evergreen)** — per riempire il calendario/progetti/blog su tutti i mesi:
  data fissa nell'anno corrente via `CONCAT(YEAR(CURDATE()), '-MM-DD HH:MM:SS')`
  (o `MAKEDATE(YEAR(CURDATE()),1) + INTERVAL ...`). Nel 2027 mostra il 2027, nel 2028 il 2028.
  Gli eventi possono cadere nel passato o futuro rispetto a *oggi*: è desiderato (calendario vivo).
- **Attività recente (relativa)** — per ciò che deve stare *vicino a oggi*: campanella, presence,
  ultimi messaggi chat, task "in scadenza tra N giorni" → `NOW()` / `CURDATE() ± INTERVAL`.

## 2. Punto 1 — Cast & profili completi

### 2.1 Cast (test_users.sql — riscrittura)
| ID | Nome | Naz | Sesso | Ruolo | username | lang |
|----|------|-----|-------|-------|----------|------|
| 3 | Giulia Ferrari | IT | F | Manager | giuliaferrari | it |
| 4 | James Whitaker | EN | M | Manager | jameswhitaker | en |
| 5 | Anna Weber | DE | F | Manager | annaweber | de |
| 6 | Marco Bianchi | IT | M | User | marcobianchi | it |
| 7 | Emily Turner | EN | F | User | emilyturner | en |
| 8 | Julien Moreau | FR | M | User | julienmoreau | fr |
| 9 | Camille Laurent | FR | F | User | camillelaurent | fr |
| 10 | Lukas Braun | DE | M | User | lukasbraun | de |
| 11 | Diego Herrera | ES | M | User | diegoherrera | es |
| 12 | Lucía Romero | ES | F | User | luciaromero | es |

- password = username, `must_change_password = 0`, email `username@favilla.test`.
- Hash Argon2id **rigenerati** con `C:\xampp\php\php.exe` (script una tantum, `password_hash(..., PASSWORD_ARGON2ID)`).
- `user_role`: 3,4,5 → 2 (Manager); 6-12 → 3 (User).

### 2.2 Preferenze (demo/05_preferences.sql — NUOVO, core)
- `user_preferences` per i 10 + admin(1): `language` = locale utente (mostra UI multilingua al login),
  `theme`/`theme_skin`/`primary_color`/`background_pattern`/`font_family` variati per profilo.
- `user_widget_preferences` per ognuno: layout Home personalizzato (`sort_order`/`visible`/`size`)
  usando widget_id reali del catalogo (es. `tasks.active`, `tasks.upcoming`, `calendar.upcoming`,
  `contacts.totali`, `contacts.ricorrenze`, `files.recent`, `notifications.list`, `home.quick_links`,
  `home.weekly_timeline`, `progetti.my_tasks`, `teams.unread`, `documenti.da_approvare`,
  `blog.list`, `reports.generated`, `feedback.open`, `auth.profile`…).

### 2.3 Avatars (wiring)
- `DemoSeeder`: nuova mappa `AVATARS_ASSETS` → copia in `public/uploads/avatars/`; set `users.avatar_path`.
- Copia **sempre** (core). Admin(1): avatar neutro opzionale.

### 2.4 Deliverable utente — 10 foto
Cartella **`database/seeds/demo/files/avatars/`**, `.jpg` quadrate, volto centrato:
```
demo-avatar-giuliaferrari.jpg   demo-avatar-jameswhitaker.jpg   demo-avatar-annaweber.jpg
demo-avatar-marcobianchi.jpg    demo-avatar-emilyturner.jpg     demo-avatar-julienmoreau.jpg
demo-avatar-camillelaurent.jpg  demo-avatar-lukasbraun.jpg      demo-avatar-diegoherrera.jpg
demo-avatar-luciaromero.jpg
```

## 3. Sezioni core riallineate (contenuto per TUTTI e 10)

- **10_tasks** — task personali per ogni utente, tag+checklist, kanban su tutte le colonne, date miste.
- **20_calendar** — 10-15 eventi/utente su tutto l'anno (CONCAT-year) + **eventi ricorrenti**
  (`recurrence_rule`/`recurrence_end`): standup settimanale, retrospettiva mensile, 1:1 quindicinali.
  Mix personal/role/public; alcuni "prossimi" su offset relativo.
- **30_contacts** — rubriche per-utente, categorie, **contatti geolocalizzati internazionali**
  (IT/FR/DE/ES/EN) per la mappa piena, ricorrenze (compleanni/rinnovi), 1+ share per ruolo.
- **40_files** — file per-utente, versioni, share (user/role), visibility, cartelle.
- **50_notifications** — campanella per tutti (lette/non lette), coerente con eventi reali del seed.

## 4. Punto 2 — Contenuti (moduli edition Team, caricati se abilitati)

- **60_progetti** — **4 progetti** con timeline scaglionata sull'anno (1 completato, 2 attivi in fasi
  diverse, 1 upcoming); milestone, task, dipendenze (Gantt FS), timesheet, membri dal cast.
- **70_teams** — gruppi **EN** (studio "Aurora — General" + 2 gruppi progetto) con **storia lunga**;
  **dirette recenti in lingua madre**: IT Giulia↔Marco, FR Julien↔Camille, DE Anna↔Lukas,
  ES Diego↔Lucía, + 1 diretta EN James↔Emily. Feature coperte: reply/citazioni, ≥1 menzione,
  reazioni, 1 pin, 1 messaggio **modificato** con storico (`teams_message_edits`), messaggi **system**,
  **2-3 immagini** + **1 PDF** via `teams_message_attachments` (file fisici + `size_bytes` reali).
  → `DemoSeeder`: mappa `TEAMS_ASSETS` → dir upload Teams (da verificare in implementazione).
- **80_documenti** — 5-6 documenti col ciclo completo (bozza→controllo→approvazione→pubblicato→
  archiviato), riallineati a cast/date; **grant `role_permission` Documenti al ruolo Manager
  (solo demo, INSERT IGNORE dentro questa sezione)** così il workflow è ri-percorribile come manager.
- **90_blog** — **~10 articoli EN**, categorie/tag/commenti EN, 1 `scheduled` (`publish_at` futuro),
  1 pinnato, `cover_image` (placeholder generati). → mappa `BLOG_ASSETS` → dir upload blog.

## 5. Moduli oggi scoperti (scope Pieno)

Sezioni **core** nuove (quei moduli sono base, sempre presenti):

- **15_reports** — 2-3 `report_templates` + 1-2 `report_style_presets` + qualche `report_history`.
- **55_feedback** — `feedback` (bug/idea/domanda in stati open/triaged/resolved) +
  `user_notification_preferences` per-utente (eventi/canali).
- **58_system** — `healthcheck_runs` (storico verde + 1 warning), `backup_history`
  (rende reale il "backup completato" delle notifiche), `scheduler_jobs` + `scheduler_log`.
- **59_integrations** — **inerte**: 1 `webhook_endpoints` **disabilitato** verso URL fittizio +
  `webhook_deliveries` simulate; eventuale token API demo **evidentemente finto/revocato**
  (nessun segreto reale).

> Numerazione file indicativa (05/15/55/58/59): riordinabile purché le dipendenze FK siano rispettate.

## 6. `DemoSeeder.php` — modifiche

- `CORE_SECTIONS` += `05_preferences.sql`, `15_reports.sql`, `55_feedback.sql`, `58_system.sql`,
  `59_integrations.sql`.
- `MODULE_SECTIONS` invariato (60-90).
- Nuove mappe copia asset: `AVATARS_ASSETS`→`public/uploads/avatars/` (core),
  `TEAMS_ASSETS`→dir Teams (in sezione Teams), `BLOG_ASSETS`→dir blog (in sezione Blog);
  `FILES_ASSETS`/`DOCUMENTI_ASSETS` aggiornate.
- `size_bytes`/`checksum_sha256` reali per ogni asset che passa da tabelle con checksum
  (files, documenti_files, teams_message_attachments): rigenerati da script.

## 7. Nuovo comando `demo:reset`

- `app/Cli/Commands/DemoResetCommand.php` + registrazione in `app/Cli/Console.php`.
- Azione: elimina righe `id >= 9001` nelle tabelle demo (ordine child→parent),
  elimina `user_role`/`user_preferences`/`user_widget_preferences` per utenti 3-12,
  elimina utenti 3-12 (CASCADE ripulisce il residuo per-utente), rimuove gli asset copiati,
  cancella il guard `demo_data_loaded`. Belt-and-suspenders per FK `SET NULL`.
- Flag `--yes` per non interattivo.

## 8. Bibbia narrativa (artefatto — si fa PER PRIMO)

`database/seeds/demo/NARRATIVE.md`: sorgente di verità unica da cui derivano tutte le sezioni.
- Studio "Aurora Studio" = studio digitale, **team remoto internazionale**.
- Cast (§2.1) con ruoli/lingua/id.
- Clienti: Rossetti Calzature (IT), Bottega Verde Bio (IT) + **1-2 internazionali** (geo).
- 4 progetti → mapping cliente/owner/membri/milestone + timeline sull'anno.
- Timeline eventi chiave (kickoff, go-live, campagna chiusa, offsite).
- Mappa cross-modulo: contatto = cliente progetto = controparte documento = soggetto articolo =
  tema chat. Chiude l'incoerenza dei "nomi vecchi" nei testi liberi.

## 9. Test — estendere `DemoSeederIntegrationTest`

- Tutte le sezioni nuove → `ok`; tabelle nuove non vuote (report_templates, feedback,
  user_preferences≥10, user_widget_preferences, healthcheck_runs, backup_history,
  scheduler_jobs, webhook_endpoints).
- No orfani FK: `teams_message_attachments`→messages, `teams_message_mentions`→messages/users,
  `teams_message_reactions`→messages/users, `user_widget_preferences`→users.
- `avatar_path` valorizzato per tutti e 10 + file copiati in `public/uploads/avatars/`.
- Coerenza checksum/size per asset (files, documenti, teams attachments) vs file reali.
- **utf8mb4 round-trip**: una diretta in lingua madre con accenti (é/ü/ß/ñ/ç) torna identica.
- Ruolo Manager ha i permessi Documenti (role_permission).
- Nuovo test `demo:reset` → tabelle pulite + guard rimosso.

## 10. Docs & housekeeping

- `DemoSeedCommand`: aggiornare il blocco "Login dimostrativi" (nuovo cast).
- README / CHANGELOG: voce dedicata.
- Verificare relazione con `db_conversion.md` (untracked in working tree).
- `docs/contracts/data.md` se cita i dati demo.

## 11. Ordine di esecuzione (fasi)

- **A.** Bibbia narrativa (§8) — fondamenta.
- **B.** Cast: test_users + 05_preferences + avatars wiring + rigenerazione hash.
- **C.** Sezioni core (§3) col motore date + eventi ricorrenti.
- **D.** Contenuti (§4) + asset (immagini teams, cover blog, placeholder generati) + checksum.
- **E.** Moduli scoperti (§5).
- **F.** `DemoSeeder` wiring (§6) + `demo:reset` (§7).
- **G.** Estendere il test (§9) + run integrazione MariaDB + suite completa.
- **H.** Docs/CHANGELOG (§10).
