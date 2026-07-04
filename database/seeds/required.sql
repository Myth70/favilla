-- ============================================================================
-- Favilla v2.0.0 — Seed dati obbligatori (consolidato)
-- ============================================================================
-- Dati minimi per una nuova installazione completa.
-- Uso: php database/migrate.php --fresh (eseguito automaticamente dopo schema.sql)
-- Generato dal DB reale — 2026-04-16
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


-- ------------------------------------------------------------------
-- 1. Ruoli base
-- ------------------------------------------------------------------

INSERT IGNORE INTO `roles` (`id`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES (1,'Administrator','admin','Accesso completo a tutte le funzionalità','2026-03-21 22:50:14','2026-03-21 22:50:14'),(2,'Manager','manager','Gestione utenti e moduli principali','2026-03-21 22:50:14','2026-03-21 22:50:14'),(3,'User','user','Accesso base alle funzionalità standard','2026-03-21 22:50:14','2026-03-21 22:50:14');


-- ------------------------------------------------------------------
-- 2. Permessi (tutti i moduli)
-- ------------------------------------------------------------------

INSERT IGNORE INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`, `updated_at`) VALUES (1,'Visualizza utenti','admin.users.view','Admin','2026-03-21 23:50:14','2026-03-21 23:50:14'),(2,'Crea utenti','admin.users.create','Admin','2026-03-21 23:50:14','2026-03-21 23:50:14'),(3,'Modifica utenti','admin.users.edit','Admin','2026-03-21 23:50:14','2026-03-21 23:50:14'),(4,'Elimina utenti','admin.users.delete','Admin','2026-03-21 23:50:14','2026-03-21 23:50:14'),(5,'Accedi come utente','admin.users.impersonate','Admin','2026-03-21 23:50:14','2026-03-21 23:50:14'),(6,'Gestisci ruoli e permessi','admin.roles.manage','Admin','2026-03-21 23:50:14','2026-03-21 23:50:14'),(7,'Visualizza log','admin.logs.view','Admin','2026-03-21 23:50:14','2026-03-21 23:50:14'),(8,'Elimina log (pulizia)','admin.logs.purge','Admin','2026-03-21 23:50:14','2026-03-21 23:50:14'),(9,'Gestisci moduli','admin.modules.manage','Admin','2026-03-21 23:50:14','2026-03-21 23:50:14'),(10,'Gestisci changelog versioni','admin.changelog.manage','Admin','2026-03-21 23:50:14','2026-03-21 23:50:14'),(11,'Gestisci impostazioni applicazione','admin.settings.manage','Admin','2026-03-21 23:50:14','2026-04-24 23:00:13'),(12,'Gestisci configurazione email','admin.mail.manage','Admin','2026-03-21 23:50:14','2026-04-24 23:00:13'),(13,'Visualizza log email','admin.mail.log','Admin','2026-03-21 23:50:14','2026-03-21 23:50:14'),(14,'Visualizza file','files.view','Files','2026-03-21 23:50:14','2026-03-21 23:50:14'),(15,'Carica file','files.create','Files','2026-03-21 23:50:14','2026-03-21 23:50:14'),(16,'Modifica metadati file','files.edit','Files','2026-03-21 23:50:14','2026-03-21 23:50:14'),(17,'Elimina propri file','files.delete','Files','2026-03-21 23:50:14','2026-03-21 23:50:14'),(18,'Amministra tutti i file','files.admin','Files','2026-03-21 23:50:14','2026-03-21 23:50:14'),(19,'Invia notifiche agli utenti (admin)','notifications.admin.send','Notifications','2026-03-21 23:50:14','2026-03-21 23:50:14'),(23,'Visualizza Health Check','healthcheck.view','HealthCheck','2026-03-21 23:50:14','2026-03-21 23:50:14'),(24,'Gestisci backup','backup.manage','Backup','2026-03-21 23:50:14','2026-03-21 23:50:14'),(25,'Scarica backup','backup.download','Backup','2026-03-21 23:50:14','2026-03-21 23:50:14'),(27,'Storico Health Check','healthcheck.history','HealthCheck','2026-03-21 23:50:14','2026-03-21 23:50:14'),(28,'Esporta Health Check','healthcheck.export','HealthCheck','2026-03-21 23:50:14','2026-03-21 23:50:14'),(34,'Visualizza Report','reports.view','Reports','2026-03-21 23:50:15','2026-03-21 23:50:15'),(35,'Crea template report','reports.create','Reports','2026-03-21 23:50:15','2026-03-21 23:50:15'),(36,'Modifica template report','reports.edit','Reports','2026-03-21 23:50:15','2026-03-21 23:50:15'),(37,'Elimina template report','reports.delete','Reports','2026-03-21 23:50:15','2026-03-21 23:50:15'),(38,'Gestisci stili report','reports.styles','Reports','2026-03-21 23:50:15','2026-03-21 23:50:15'),(39,'Amministrazione report','reports.admin','Reports','2026-03-21 23:50:15','2026-03-21 23:50:15'),(40,'Esporta dati','reports.export','Reports','2026-03-21 23:50:15','2026-03-21 23:50:15'),(41,'Gestisci modelli documento','reports.documents','Reports','2026-03-21 23:50:15','2026-04-24 23:00:13'),(53,'Visualizza Calendario','calendar.view','Calendar','2026-03-22 16:32:51','2026-03-22 16:32:51'),(54,'Crea Eventi','calendar.create','Calendar','2026-03-22 16:32:51','2026-03-22 16:32:51'),(55,'Modifica Eventi','calendar.edit','Calendar','2026-03-22 16:32:51','2026-03-22 16:32:51'),(56,'Elimina Eventi','calendar.delete','Calendar','2026-03-22 16:32:51','2026-03-22 16:32:51'),(57,'Visualizza Attività','tasks.view','Tasks','2026-03-23 01:13:40','2026-03-23 01:13:40'),(58,'Crea Attività','tasks.create','Tasks','2026-03-23 01:13:40','2026-03-23 01:13:40'),(59,'Modifica Attività','tasks.edit','Tasks','2026-03-23 01:13:40','2026-03-23 01:13:40'),(60,'Elimina Attività','tasks.delete','Tasks','2026-03-23 01:13:40','2026-03-23 01:13:40'),(61,'Visualizza Contatti','contacts.view','Contacts','2026-03-23 10:39:27','2026-03-23 10:39:27'),(62,'Crea Contatti','contacts.create','Contacts','2026-03-23 10:39:27','2026-03-23 10:39:27'),(63,'Modifica Contatti','contacts.edit','Contacts','2026-03-23 10:39:27','2026-03-23 10:39:27'),(64,'Elimina Contatti','contacts.delete','Contacts','2026-03-23 10:39:27','2026-03-23 10:39:27'),(65,'Gestisci dispatcher notifiche','notifications.admin.manage','Notifications','2026-03-23 18:49:23','2026-03-23 18:49:23'),(66,'Gestisci template notifiche','notifications.admin.templates','Notifications','2026-03-23 18:49:23','2026-03-23 18:49:23'),(67,'Gestisci bot Telegram','notifications.admin.bots','Notifications','2026-03-23 18:49:23','2026-03-23 18:49:23'),(68,'Visualizza code e delivery notifiche','notifications.admin.deliveries','Notifications','2026-03-23 18:49:23','2026-03-23 18:49:23'),(69,'Accesso modulo File','files.access','Files','2026-03-24 21:41:09','2026-03-24 21:41:09'),(181,'Visualizza Scheduler','scheduler.view','Scheduler','2026-03-29 01:27:39','2026-03-29 01:27:39'),(182,'Gestisci Scheduler','scheduler.manage','Scheduler','2026-03-29 01:27:39','2026-03-29 01:27:39'),(183,'Visualizza area sicurezza','admin.security.view','Admin','2026-04-01 21:48:44','2026-04-28 20:19:35'),(184,'Gestisci area sicurezza','admin.security.manage','Admin','2026-04-01 21:48:44','2026-04-28 20:19:35'),(187,'Gestisci policy sicurezza','admin.security.policy','Admin','2026-04-01 21:48:44','2026-04-01 21:48:44'),(193,'Visualizza inventario asset','admin.security.assets','Admin','2026-04-01 22:10:36','2026-04-01 22:10:36'),(194,'Gestisci log di sicurezza','admin.security.logs','Admin','2026-04-01 22:10:36','2026-04-28 20:19:35'),(195,'Gestisci data retention','admin.retention.manage','Admin','2026-04-01 22:26:40','2026-04-28 20:19:35'),(226,'Condividi file','files.share','Files','2026-04-02 14:57:01','2026-04-02 14:57:01'),(227,'Ripristina backup','backup.restore','Backup','2026-04-02 16:06:46','2026-04-02 16:06:46'),(408,'Importa Contatti','contacts.import','Contacts','2026-04-30 23:56:05','2026-05-24 16:24:00'),(409,'Condividi i propri Contatti per ruolo','contacts.share','Contacts','2026-05-01 01:41:12','2026-05-01 01:41:12'),(410,'Gestione Help Online','helponline.admin','HelpOnline','2026-05-01 16:18:22','2026-05-01 16:18:22'),(465,'Visualizza segnalazioni','feedback.view','Feedback','2026-05-31 15:30:52','2026-05-31 15:30:52'),(466,'Gestisci segnalazioni (triage)','feedback.manage','Feedback','2026-05-31 15:30:52','2026-05-31 15:30:52'),(509,'Simulatore pagine (dev)','admin.dev.simulator','Admin','2026-06-18 01:00:47','2026-06-18 01:00:47'),
    -- Moduli edizione Team (disabilitati di default — vedi sezione 15)
    (510,'Visualizza Progetti','progetti.view','Progetti','2026-07-03 09:50:54','2026-07-03 09:50:54'),(511,'Crea Progetti','progetti.create','Progetti','2026-07-03 09:50:54','2026-07-03 09:50:54'),(512,'Modifica Progetti','progetti.edit','Progetti','2026-07-03 09:50:54','2026-07-03 09:50:54'),(513,'Elimina Progetti','progetti.delete','Progetti','2026-07-03 09:50:54','2026-07-03 09:50:54'),(514,'Gestisci Membri Progetto','progetti.manage_members','Progetti','2026-07-03 09:50:54','2026-07-03 09:50:54'),(515,'Registra Ore Progetto','progetti.log_time','Progetti','2026-07-03 09:50:54','2026-07-03 09:50:54'),(516,'Visualizza Tutti i Progetti','progetti.view_all','Progetti','2026-07-03 09:50:54','2026-07-03 09:50:54'),(517,'Gestione Completa Progetti','progetti.manage_all','Progetti','2026-07-03 09:50:54','2026-07-03 09:50:54'),(518,'Esporta Dati Progetti','progetti.export','Progetti','2026-07-03 09:50:54','2026-07-03 09:50:54'),
    (519,'Accesso Teams (lettura, presence, mute/hide)','teams.view','Teams','2026-07-03 15:04:11','2026-07-03 15:04:15'),(520,'Invia messaggi, gestisci gruppi e membri','teams.create','Teams','2026-07-03 15:04:11','2026-07-03 15:04:15'),(521,'Modifica/elimina i propri messaggi, esci dai gruppi','teams.delete','Teams','2026-07-03 15:04:11','2026-07-03 15:04:15'),(522,'Amministra Teams (pannello, cleanup, override)','teams.admin','Teams','2026-07-03 15:04:11','2026-07-03 15:04:15'),
    (523,'Accesso modulo Documenti','documenti.access','Documenti','2026-07-03 19:27:00','2026-07-03 19:27:00'),(524,'Visualizza documenti','documenti.view','Documenti','2026-07-03 19:27:00','2026-07-03 19:27:00'),(525,'Crea documenti','documenti.create','Documenti','2026-07-03 19:27:00','2026-07-03 19:27:00'),(526,'Redazione documenti (modifica bozze, carica versioni)','documenti.redazione','Documenti','2026-07-03 19:27:00','2026-07-03 19:27:00'),(527,'Controllo documenti (step 2 workflow)','documenti.controllo','Documenti','2026-07-03 19:27:00','2026-07-03 19:27:00'),(528,'Approvazione documenti (step 3 workflow)','documenti.approvazione','Documenti','2026-07-03 19:27:00','2026-07-03 19:27:00'),(529,'Inbox documenti (workflow in entrata)','documenti.inbox','Documenti','2026-07-03 19:27:00','2026-07-03 19:27:00'),(530,'Elimina propri documenti','documenti.delete','Documenti','2026-07-03 19:27:00','2026-07-03 19:27:00'),(531,'Gestisci categorie documenti','documenti.manage_categorie','Documenti','2026-07-03 19:27:00','2026-07-03 19:27:00'),(532,'Gestisci collegamenti documenti','documenti.manage_collegamenti','Documenti','2026-07-03 19:27:00','2026-07-03 19:27:00'),(533,'Esporta elenchi documenti','documenti.export','Documenti','2026-07-03 19:27:00','2026-07-03 19:27:00'),(534,'Amministra tutti i documenti','documenti.admin','Documenti','2026-07-03 19:27:00','2026-07-03 19:27:00'),
    (535,'Visualizza Blog','blog.view','Blog','2026-07-04 00:11:31','2026-07-04 00:11:31'),(536,'Scrivi articoli Blog','blog.write','Blog','2026-07-04 00:11:31','2026-07-04 00:11:31'),(537,'Amministra Blog','blog.admin','Blog','2026-07-04 00:11:31','2026-07-04 00:11:31'),(538,'Commenta articoli Blog','blog.comment','Blog','2026-07-04 00:11:32','2026-07-04 00:11:32'),(539,'Modera commenti Blog','blog.comment.moderate','Blog','2026-07-04 00:11:32','2026-07-04 00:11:32');


-- ------------------------------------------------------------------
-- 3. Assegnazione permessi → ruoli
-- ------------------------------------------------------------------

-- Administrator: tutti i permessi (dinamico, copre sempre i futuri)
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
    SELECT 1, `id` FROM `permissions`;

-- Manager e User: moduli user-facing
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
    SELECT 2, `id` FROM `permissions`
    WHERE `slug` IN (
        'tasks.view','tasks.create','tasks.edit','tasks.delete',
        'calendar.view','calendar.create','calendar.edit','calendar.delete',
        'contacts.view','contacts.create','contacts.edit','contacts.delete',
        'files.access','files.view','files.create','files.edit','files.delete','files.share',
        'blog.view','blog.comment',
        'teams.view','teams.create','teams.delete'
    );

INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
    SELECT 3, `id` FROM `permissions`
    WHERE `slug` IN (
        'tasks.view','tasks.create','tasks.edit','tasks.delete',
        'calendar.view','calendar.create','calendar.edit','calendar.delete',
        'contacts.view','contacts.create','contacts.edit','contacts.delete',
        'files.access','files.view','files.create','files.edit','files.delete','files.share',
        'blog.view','blog.comment',
        'teams.view','teams.create','teams.delete'
    );


-- ------------------------------------------------------------------
-- 4. Utente amministratore di default (password: Admin123!)
-- ------------------------------------------------------------------

-- Password: Admin123! (Argon2id) — must_change_password=1 forza il cambio al primo accesso
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `username`, `password`, `is_active`, `must_change_password`) VALUES
    (1, 'Amministratore', 'admin@intranet', 'admin',
     '$argon2id$v=19$m=65536,t=4,p=1$T2ZKR1Zlb2V0VnJKZHFabA$c5vZFTKKcWCJcO8YqO85XmWpKRVZUXHj7OIe2trtXVE',
     1, 1);


-- ------------------------------------------------------------------
-- 5. Ruolo → utente admin
-- ------------------------------------------------------------------

INSERT IGNORE INTO `user_role` (`user_id`, `role_id`) VALUES (1,1);


-- ------------------------------------------------------------------
-- 6. Impostazioni di default
-- ------------------------------------------------------------------

INSERT IGNORE INTO `app_settings` (`key`, `value`, `type`, `group`, `label`) VALUES
    -- Generale
    ('app_name',                    'Favilla',             'string', 'general',  'Nome applicazione'),
    ('app_footer',                  '',                     'string', 'general',  'Testo footer personalizzato'),
    ('app_edition',                 'developer',            'string', 'general',  'Edizione installazione'),
    -- Sistema
    ('app_env',                     'production',           'string', 'system',   'Ambiente applicazione'),
    ('app_debug',                   '0',                    'bool',   'system',   'Modalità debug'),
    ('maintenance_mode',            '0',                    'bool',   'system',   'Modalità manutenzione'),
    ('impersonation_timeout',       '30',                   'int',    'system',   'Timeout impersonazione (minuti)'),
    -- Mail
    ('mail_driver',                 'log',                  'string', 'mail',     'Driver email (log/smtp)'),
    ('mail_from_address',           'noreply@favilla.local','string','mail',     'Indirizzo mittente'),
    ('mail_from_name',              'Favilla',             'string', 'mail',     'Nome mittente'),
    ('smtp_host',                   '',                     'string', 'mail',     'Host SMTP'),
    ('smtp_port',                   '587',                  'int',    'mail',     'Porta SMTP'),
    ('smtp_username',               '',                     'string', 'mail',     'Username SMTP'),
    ('smtp_password',               '',                     'string', 'mail',     'Password SMTP'),
    ('smtp_encryption',             'tls',                  'string', 'mail',     'Crittografia (tls/ssl/none)'),
    -- Sicurezza — password policy
    ('password_policy_enabled',     'true',                 'bool',   'security', 'Abilita policy password ISO 27001'),
    ('password_min_length',         '12',                   'int',    'security', 'Lunghezza minima password'),
    ('password_require_upper',      'true',                 'bool',   'security', 'Richiedi lettera maiuscola'),
    ('password_require_lower',      'true',                 'bool',   'security', 'Richiedi lettera minuscola'),
    ('password_require_digit',      'true',                 'bool',   'security', 'Richiedi almeno un numero'),
    ('password_require_special',    'true',                 'bool',   'security', 'Richiedi carattere speciale'),
    ('password_max_age_days',       '90',                   'int',    'security', 'Scadenza password (giorni, 0=disabilitata)'),
    ('password_history_count',      '5',                    'int',    'security', 'Numero password precedenti non riutilizzabili'),
    -- Sicurezza — MFA
    ('mfa_totp_enabled',            'true',                 'bool',   'security', 'Abilita TOTP/MFA'),
    ('mfa_required_for_admin',      'false',                'bool',   'security', 'Obbliga MFA per ruolo admin'),
    ('mfa_setup_grace_period_days', '0',                    'int',    'security', 'Giorni di grazia per setup MFA (0 = nessun obbligo)'),
    ('mfa_backup_codes_count',      '10',                   'int',    'security', 'Numero backup codes per utente'),
    -- Sicurezza — sessioni e incidenti
    ('session_max_concurrent',      '3',                    'int',    'security', 'Sessioni concorrenti massime per utente'),
    ('security_failed_login_threshold','10',                'int',    'security', 'Soglia tentativi login falliti per allarme'),
    ('security_failed_login_window','15',                   'int',    'security', 'Finestra temporale allarme (minuti)'),
    ('security_incident_notify',    'true',                 'bool',   'security', 'Notifica admin su incidenti di sicurezza'),
    -- Sicurezza — log e retention
    ('log_rotation_enabled',        'true',                 'bool',   'security', 'Rotazione automatica log abilitata'),
    ('log_retention_days',          '365',                  'int',    'security', 'Retention file di log (giorni)'),
    ('key_rotation_max_days',       '180',                  'int',    'security', 'Intervallo Rotazione Chiavi (giorni)'),
    -- GDPR
    ('consent_required_on_login',   'true',                 'bool',   'security', 'Richiedi consensi obbligatori al login'),
    -- Report
    ('reports_encrypt_at_rest',     'false',                'bool',   'security', 'Cifra Report a Riposo');


-- ------------------------------------------------------------------
-- 7. Template email
-- ------------------------------------------------------------------

INSERT IGNORE INTO `mail_templates` (`id`, `slug`, `name`, `subject`, `body_html`, `variables`, `created_at`, `updated_at`) VALUES (1,'password-reset','Reset Password','Reset della password','<p>Ciao {{name}},</p><p>Clicca sul link per resettare la password:</p><p><a href=\"{{link}}\">{{link}}</a></p><p>Il link scade tra {{expiry}} minuti.</p>','{{name}},{{link}},{{expiry}}','2026-03-21 22:50:14','2026-03-21 22:50:14'),(2,'notification','Notifica Generica','Notifica da {{app_name}}','<p>Ciao {{name}},</p><p>{{message}}</p>','{{name}},{{message}},{{app_name}}','2026-03-21 22:50:14','2026-03-21 22:50:14');


-- ------------------------------------------------------------------
-- 8. Canali notifiche
-- ------------------------------------------------------------------

INSERT IGNORE INTO `notification_channels` (`id`, `slug`, `name`, `description`, `is_enabled`, `sort_order`, `created_at`, `updated_at`) VALUES (1,'in_app','In App','Notifiche nella campanella e nella lista interna',1,10,'2026-03-23 17:49:23','2026-03-23 17:49:23'),(2,'email','Email','Invio tramite il sistema mail configurato',1,20,'2026-03-23 17:49:23','2026-03-23 17:49:23'),(3,'telegram','Telegram','Invio tramite bot Telegram collegato all’utente',1,30,'2026-03-23 17:49:23','2026-03-23 17:49:23');


-- ------------------------------------------------------------------
-- 9. Tipi di evento notifiche
-- ------------------------------------------------------------------

INSERT IGNORE INTO `notification_event_types` (`id`, `slug`, `module_slug`, `name`, `description`, `context_schema`, `source`, `default_level`, `icon`, `color`, `is_system`, `created_at`, `updated_at`) VALUES (44,'tasks.task_overdue','tasks','Attivita scaduta','Inviata quando un\'attivita supera la data di scadenza','{\"task_id\":\"ID attivita\",\"task_title\":\"Titolo attivita\",\"due_date\":\"Data di scadenza\"}','module_json','warning','fa-solid fa-clock','#7C3AED',0,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(45,'tasks.task_due_today','tasks','Attivita in scadenza oggi','Inviata il giorno della scadenza di un\'tasks','{\"task_id\":\"ID attivita\",\"task_title\":\"Titolo attivita\",\"due_date\":\"Data di scadenza\"}','module_json','info','fa-solid fa-calendar-day','#DC2626',0,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(46,'backup.completed','backup','Backup completato','Inviata quando un backup termina con successo','{\"filename\":\"Nome file backup\",\"size\":\"Dimensione backup\",\"size_mb\":\"Dimensione in MB\",\"table_count\":\"Numero tabelle incluse\"}','module_json','success','fa-solid fa-database','#16A34A',0,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(49,'admin.user_reactivated','admin','Utente riattivato','Inviata quando un amministratore riattiva un account utente','{\"user_id\":\"ID utente riattivato\",\"activated_by\":\"ID admin che ha riattivato\"}','module_json','success','fa-solid fa-user-check','#16A34A',0,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(50,'calendar.shared_event_created','calendar','Evento condiviso creato','Inviata quando viene creato un evento condiviso','{\"event_id\":\"ID evento\",\"event_title\":\"Titolo evento\",\"start_label\":\"Data/ora inizio\",\"location\":\"Luogo\"}','module_json','info','fa-solid fa-calendar-plus','#0D9488',0,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(53,'contacts.reminder_due','contacts','Promemoria contatto','Inviata per ricorrenze e reminder contatti','{\"contatto_id\":\"ID contatto\",\"titolo\":\"Titolo reminder\",\"contatto_nome\":\"Nome contatto\",\"data_label\":\"Data evento\",\"giorni\":\"Giorni mancanti\"}','module_json','info','fa-solid fa-address-book','#EA580C',0,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(54,'health_check.failures_detected','health_check','Errori Health Check rilevati','Inviata quando vengono rilevati check falliti','{\"failure_count\":\"Numero check falliti\",\"failed_checks_text\":\"Elenco sintetico check falliti\"}','module_json','danger','fa-solid fa-heart-pulse',NULL,0,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(74,'notifications.manual_send','notifications','Invio manuale admin','Notifica inviata manualmente dall\'amministrazione','{\"title\":\"Titolo notifica\",\"body\":\"Corpo notifica\",\"type\":\"Livello notifica\"}','module_json','info','fa-solid fa-paper-plane',NULL,0,'2026-03-23 20:19:16','2026-04-13 21:10:32'),(76,'system.direct_send','notifications','Invio diretto sistema','Evento generico usato dall\'API legacy send/sendToRole','{\"title\":\"Titolo notifica\",\"body\":\"Corpo notifica\",\"type\":\"Livello notifica\"}','module_json','info','fa-solid fa-bell','#475569',0,'2026-03-24 13:03:31','2026-04-13 21:10:32'),(78,'auth.nuova_registrazione','auth','Nuova richiesta di registrazione','Inviata agli amministratori quando un nuovo utente completa la registrazione e attende approvazione','{\"name\":\"Nome e cognome del nuovo utente\",\"username\":\"Username scelto\",\"email\":\"Indirizzo email\"}','module_json','info','fa-solid fa-user-plus','#7C3AED',0,'2026-03-25 13:06:54','2026-04-13 21:10:32'),(84,'calendar.event_reminder','calendar','Promemoria evento','Inviata dallo scheduler prima dell\'inizio di un evento con reminder configurato','{\"event_id\":\"ID evento\",\"event_title\":\"Titolo evento\",\"start_label\":\"Data/ora inizio\",\"location\":\"Luogo\",\"minutes_before\":\"Minuti prima dell\'evento\"}','module_json','info','fa-bell','#f59e0b',0,'2026-03-29 00:28:07','2026-04-13 21:10:32'),(85,'security.incident_detected','Admin','Incidente di sicurezza rilevato','Notifica inviata agli amministratori quando viene rilevato un incidente di sicurezza (brute-force, CSRF, accesso non autorizzato)','{\"incident_type\": \"Tipo di incidente\", \"incident_title\": \"Titolo incidente\", \"ip\": \"Indirizzo IP\", \"attempts\": \"Numero tentativi\"}','dynamic','danger','fa-solid fa-shield-exclamation',NULL,0,'2026-04-01 19:50:11','2026-04-01 19:50:11');


-- ------------------------------------------------------------------
-- 10. Bindings evento → canale
-- ------------------------------------------------------------------

INSERT IGNORE INTO `notification_event_channel_bindings` (`id`, `event_type_id`, `channel_slug`, `is_enabled`, `subject_template`, `body_template`, `layout_config`, `created_at`, `updated_at`) VALUES (130,49,'in_app',1,'Account riattivato','Il tuo account Favilla è stato riattivato dall\'amministratore. Puoi accedere normalmente a tutte le funzionalità.',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(131,45,'in_app',1,'Scadenza oggi: {{task_title}}','L\'attività scade oggi ({{due_date}}). Ricorda di completarla o aggiornarla.',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(132,44,'in_app',1,'Attività scaduta: {{task_title}}','L\'attività era in scadenza il {{due_date}} e non risulta ancora completata.',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(133,46,'in_app',1,'Backup completato con successo','File: {{filename}} — Dimensione: {{size}} — Tabelle incluse: {{table_count}}',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(136,50,'in_app',1,'Nuovo evento condiviso: {{event_title}}','📅 {{start_label}}  📍 {{location}}',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(137,53,'in_app',1,'{{titolo}}','Contatto: {{contatto_nome}} — Data: {{data_label}}',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(140,54,'in_app',1,'🚨 {{failure_count}} anomalia/e rilevata/e — Health Check','Check falliti: {{failed_checks_text}}',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(145,49,'email',0,'Il tuo account Favilla è stato riattivato','<!DOCTYPE html>\n<html lang=\"it\">\n<head><meta charset=\"UTF-8\"></head>\n<body style=\"margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#1e293b;\">\n<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"padding:32px 0;\">\n  <tr><td align=\"center\">\n    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\"\n           style=\"background:#ffffff;border-radius:8px;overflow:hidden;\n                  border:1px solid #e2e8f0;max-width:600px;\">\n      <tr>\n        <td style=\"background:#16a34a;padding:28px 32px;text-align:center;\">\n          <div style=\"font-size:42px;line-height:1;\">✅</div>\n          <div style=\"margin-top:12px;color:#ffffff;font-size:21px;font-weight:700;\n                      line-height:1.4;\">Account riattivato</div>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"padding:32px 40px;\">\n          <p style=\"margin:0;font-size:15px;line-height:1.7;color:#374151;\">Il tuo account Favilla è stato riattivato dall\'amministratore. Puoi accedere normalmente a tutte le funzionalità dell\'intranet aziendale.</p>\n        <p style=\"text-align:center;margin:28px 0 0;\">\n          <a href=\"{{link}}\"\n             style=\"display:inline-block;background:#16a34a;color:#ffffff;\n                    text-decoration:none;padding:12px 28px;border-radius:6px;\n                    font-weight:700;font-size:15px;\">Accedi a Favilla &#8594;</a>\n        </p>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"background:#f8fafc;padding:18px 40px;text-align:center;\n                   font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;\">\n          Favilla Intranet &mdash; Notifica automatica. Non rispondere a questa email.\n        </td>\n      </tr>\n    </table>\n  </td></tr>\n</table>\n</body></html>',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(146,45,'email',0,'Scadenza oggi: {{task_title}}','<!DOCTYPE html>\n<html lang=\"it\">\n<head><meta charset=\"UTF-8\"></head>\n<body style=\"margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#1e293b;\">\n<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"padding:32px 0;\">\n  <tr><td align=\"center\">\n    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\"\n           style=\"background:#ffffff;border-radius:8px;overflow:hidden;\n                  border:1px solid #e2e8f0;max-width:600px;\">\n      <tr>\n        <td style=\"background:#0891b2;padding:28px 32px;text-align:center;\">\n          <div style=\"font-size:42px;line-height:1;\">📅</div>\n          <div style=\"margin-top:12px;color:#ffffff;font-size:21px;font-weight:700;\n                      line-height:1.4;\">Attività in scadenza oggi</div>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"padding:32px 40px;\">\n          <p style=\"margin:0;font-size:15px;line-height:1.7;color:#374151;\">Un\'attività assegnata a te è in scadenza oggi. Accedi a Favilla per visualizzarla e aggiornarla.</p>\n        <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\"\n               style=\"margin-top:20px;border-top:1px solid #e2e8f0;font-size:14px;color:#374151;\">\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Attività</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{task_title}}</td></tr>\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Scadenza</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{due_date}}</td></tr>\n        </table>\n        <p style=\"text-align:center;margin:28px 0 0;\">\n          <a href=\"{{link}}\"\n             style=\"display:inline-block;background:#0891b2;color:#ffffff;\n                    text-decoration:none;padding:12px 28px;border-radius:6px;\n                    font-weight:700;font-size:15px;\">Vedi attività &#8594;</a>\n        </p>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"background:#f8fafc;padding:18px 40px;text-align:center;\n                   font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;\">\n          Favilla Intranet &mdash; Notifica automatica. Non rispondere a questa email.\n        </td>\n      </tr>\n    </table>\n  </td></tr>\n</table>\n</body></html>',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(147,44,'email',0,'Attività scaduta: {{task_title}}','<!DOCTYPE html>\n<html lang=\"it\">\n<head><meta charset=\"UTF-8\"></head>\n<body style=\"margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#1e293b;\">\n<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"padding:32px 0;\">\n  <tr><td align=\"center\">\n    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\"\n           style=\"background:#ffffff;border-radius:8px;overflow:hidden;\n                  border:1px solid #e2e8f0;max-width:600px;\">\n      <tr>\n        <td style=\"background:#f97316;padding:28px 32px;text-align:center;\">\n          <div style=\"font-size:42px;line-height:1;\">⚠️</div>\n          <div style=\"margin-top:12px;color:#ffffff;font-size:21px;font-weight:700;\n                      line-height:1.4;\">Attività scaduta</div>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"padding:32px 40px;\">\n          <p style=\"margin:0;font-size:15px;line-height:1.7;color:#374151;\">Un\'attività assegnata a te ha superato la data di scadenza senza essere completata. Intervieni al più presto.</p>\n        <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\"\n               style=\"margin-top:20px;border-top:1px solid #e2e8f0;font-size:14px;color:#374151;\">\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Attività</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{task_title}}</td></tr>\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Scadenza</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{due_date}}</td></tr>\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Stato</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">Scaduta — in ritardo</td></tr>\n        </table>\n        <p style=\"text-align:center;margin:28px 0 0;\">\n          <a href=\"{{link}}\"\n             style=\"display:inline-block;background:#f97316;color:#ffffff;\n                    text-decoration:none;padding:12px 28px;border-radius:6px;\n                    font-weight:700;font-size:15px;\">Vedi attività &#8594;</a>\n        </p>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"background:#f8fafc;padding:18px 40px;text-align:center;\n                   font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;\">\n          Favilla Intranet &mdash; Notifica automatica. Non rispondere a questa email.\n        </td>\n      </tr>\n    </table>\n  </td></tr>\n</table>\n</body></html>',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(148,46,'email',0,'Backup database completato','<!DOCTYPE html>\n<html lang=\"it\">\n<head><meta charset=\"UTF-8\"></head>\n<body style=\"margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#1e293b;\">\n<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"padding:32px 0;\">\n  <tr><td align=\"center\">\n    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\"\n           style=\"background:#ffffff;border-radius:8px;overflow:hidden;\n                  border:1px solid #e2e8f0;max-width:600px;\">\n      <tr>\n        <td style=\"background:#16a34a;padding:28px 32px;text-align:center;\">\n          <div style=\"font-size:42px;line-height:1;\">🗄️</div>\n          <div style=\"margin-top:12px;color:#ffffff;font-size:21px;font-weight:700;\n                      line-height:1.4;\">Backup completato con successo</div>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"padding:32px 40px;\">\n          <p style=\"margin:0;font-size:15px;line-height:1.7;color:#374151;\">Il backup automatico del database Favilla è stato eseguito correttamente.</p>\n        <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\"\n               style=\"margin-top:20px;border-top:1px solid #e2e8f0;font-size:14px;color:#374151;\">\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">File</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{filename}}</td></tr>\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Dimensione</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{size}}</td></tr>\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Tabelle incluse</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{table_count}}</td></tr>\n        </table>\n        <p style=\"text-align:center;margin:28px 0 0;\">\n          <a href=\"{{link}}\"\n             style=\"display:inline-block;background:#16a34a;color:#ffffff;\n                    text-decoration:none;padding:12px 28px;border-radius:6px;\n                    font-weight:700;font-size:15px;\">Gestisci backup &#8594;</a>\n        </p>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"background:#f8fafc;padding:18px 40px;text-align:center;\n                   font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;\">\n          Favilla Intranet &mdash; Notifica automatica. Non rispondere a questa email.\n        </td>\n      </tr>\n    </table>\n  </td></tr>\n</table>\n</body></html>',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(151,50,'email',0,'Nuovo evento condiviso: {{event_title}}','<!DOCTYPE html>\n<html lang=\"it\">\n<head><meta charset=\"UTF-8\"></head>\n<body style=\"margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#1e293b;\">\n<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"padding:32px 0;\">\n  <tr><td align=\"center\">\n    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\"\n           style=\"background:#ffffff;border-radius:8px;overflow:hidden;\n                  border:1px solid #e2e8f0;max-width:600px;\">\n      <tr>\n        <td style=\"background:#2563eb;padding:28px 32px;text-align:center;\">\n          <div style=\"font-size:42px;line-height:1;\">📅</div>\n          <div style=\"margin-top:12px;color:#ffffff;font-size:21px;font-weight:700;\n                      line-height:1.4;\">Evento condiviso con te</div>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"padding:32px 40px;\">\n          <p style=\"margin:0;font-size:15px;line-height:1.7;color:#374151;\">È stato creato un nuovo evento nel Calendario aziendale e condiviso con te.</p>\n        <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\"\n               style=\"margin-top:20px;border-top:1px solid #e2e8f0;font-size:14px;color:#374151;\">\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Evento</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{event_title}}</td></tr>\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Data/Ora</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{start_label}}</td></tr>\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Luogo</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{location}}</td></tr>\n        </table>\n        <p style=\"text-align:center;margin:28px 0 0;\">\n          <a href=\"{{link}}\"\n             style=\"display:inline-block;background:#2563eb;color:#ffffff;\n                    text-decoration:none;padding:12px 28px;border-radius:6px;\n                    font-weight:700;font-size:15px;\">Vedi nel Calendario &#8594;</a>\n        </p>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"background:#f8fafc;padding:18px 40px;text-align:center;\n                   font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;\">\n          Favilla Intranet &mdash; Notifica automatica. Non rispondere a questa email.\n        </td>\n      </tr>\n    </table>\n  </td></tr>\n</table>\n</body></html>',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(152,53,'email',0,'Promemoria: {{titolo}}','<!DOCTYPE html>\n<html lang=\"it\">\n<head><meta charset=\"UTF-8\"></head>\n<body style=\"margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#1e293b;\">\n<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"padding:32px 0;\">\n  <tr><td align=\"center\">\n    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\"\n           style=\"background:#ffffff;border-radius:8px;overflow:hidden;\n                  border:1px solid #e2e8f0;max-width:600px;\">\n      <tr>\n        <td style=\"background:#2563eb;padding:28px 32px;text-align:center;\">\n          <div style=\"font-size:42px;line-height:1;\">🔔</div>\n          <div style=\"margin-top:12px;color:#ffffff;font-size:21px;font-weight:700;\n                      line-height:1.4;\">Promemoria contatto</div>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"padding:32px 40px;\">\n          <p style=\"margin:0;font-size:15px;line-height:1.7;color:#374151;\">Hai un promemoria attivo per un contatto in agenda.</p>\n        <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\"\n               style=\"margin-top:20px;border-top:1px solid #e2e8f0;font-size:14px;color:#374151;\">\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Evento</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{titolo}}</td></tr>\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Contatto</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{contatto_nome}}</td></tr>\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Data</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{data_label}}</td></tr>\n        </table>\n        <p style=\"text-align:center;margin:28px 0 0;\">\n          <a href=\"{{link}}\"\n             style=\"display:inline-block;background:#2563eb;color:#ffffff;\n                    text-decoration:none;padding:12px 28px;border-radius:6px;\n                    font-weight:700;font-size:15px;\">Apri contatto &#8594;</a>\n        </p>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"background:#f8fafc;padding:18px 40px;text-align:center;\n                   font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;\">\n          Favilla Intranet &mdash; Notifica automatica. Non rispondere a questa email.\n        </td>\n      </tr>\n    </table>\n  </td></tr>\n</table>\n</body></html>',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(155,54,'email',0,'[ALLERTA] {{failure_count}} check falliti — Health Check Favilla','<!DOCTYPE html>\r\n<html lang=\"it\">\r\n<head><meta charset=\"UTF-8\"></head>\r\n<body style=\"margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#1e293b;\">\r\n<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"padding:32px 0;\">\r\n  <tr><td align=\"center\">\r\n    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\"\r\n           style=\"background:#ffffff;border-radius:8px;overflow:hidden;\r\n                  border:1px solid #e2e8f0;max-width:600px;\">\r\n      <tr>\r\n        <td style=\"background:#dc2626;padding:28px 32px;text-align:center;\">\r\n          <div style=\"font-size:42px;line-height:1;\">🚨</div>\r\n          <div style=\"margin-top:12px;color:#ffffff;font-size:21px;font-weight:700;\r\n                      line-height:1.4;\">{{failure_count}} anomalia/e rilevata/e</div>\r\n        </td>\r\n      </tr>\r\n      <tr>\r\n        <td style=\"padding:32px 40px;\">\r\n          <p style=\"margin:0;font-size:15px;line-height:1.7;color:#374151;\">Il sistema Health Check di Favilla ha rilevato delle anomalie. Si consiglia di intervenire tempestivamente.</p>\r\n        <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\"\r\n               style=\"margin-top:20px;border-top:1px solid #e2e8f0;font-size:14px;color:#374151;\">\r\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Check falliti</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{failure_count}}</td></tr>\n          <tr><td style=\"padding:7px 0;color:#64748b;white-space:nowrap;vertical-align:top;padding-right:16px;font-size:13px;\">Dettaglio</td><td style=\"padding:7px 0;font-weight:600;color:#1e293b;\">{{failed_checks_text}}</td></tr>\r\n        </table>\r\n        <p style=\"text-align:center;margin:28px 0 0;\">\r\n          <a href=\"{{link}}\"\r\n             style=\"display:inline-block;background:#dc2626;color:#ffffff;\r\n                    text-decoration:none;padding:12px 28px;border-radius:6px;\r\n                    font-weight:700;font-size:15px;\">Vai a Health Check &#8594;</a>\r\n        </p>\r\n        </td>\r\n      </tr>\r\n      <tr>\r\n        <td style=\"background:#f8fafc;padding:18px 40px;text-align:center;\r\n                   font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;\">\r\n          Favilla Intranet &mdash; Notifica automatica. Non rispondere a questa email.\r\n        </td>\r\n      </tr>\r\n    </table>\r\n  </td></tr>\r\n</table>\r\n</body></html>',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(160,49,'telegram',0,'✅ Account riattivato','Il tuo account Favilla è stato riattivato dall\'amministratore.\nPuoi accedere normalmente.\n\n{{link}}',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(161,45,'telegram',0,'📅 Scadenza oggi','L\'attività «{{task_title}}» scade oggi ({{due_date}}).\nRicorda di completarla o aggiornarla.\n\n{{link}}',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(162,44,'telegram',0,'⚠️ Attività scaduta','L\'attività «{{task_title}}» era in scadenza il {{due_date}} e non risulta ancora completata.\n\n{{link}}',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(163,46,'telegram',0,'✅ Backup completato','Backup database completato con successo.\n📁 File: {{filename}}\n📦 Dimensione: {{size}}\n🗃 Tabelle: {{table_count}}\n\n{{link}}',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(166,50,'telegram',0,'📅 Nuovo evento condiviso','Un nuovo evento è stato condiviso con te:\n\n📌 {{event_title}}\n🗓 {{start_label}}\n📍 {{location}}\n\n{{link}}',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(167,53,'telegram',0,'🔔 Promemoria contatto','{{titolo}}\n\n👤 Contatto: {{contatto_nome}}\n📅 Data: {{data_label}}\n\n{{link}}',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(170,54,'telegram',0,'🚨 Anomalie Health Check','❌ {{failure_count}} check falliti rilevati su Favilla.\n\nCheck: {{failed_checks_text}}\n\n{{link}}',NULL,'2026-03-23 20:09:16','2026-04-13 21:10:32'),(178,74,'in_app',1,'{{manual_title}}','{{manual_body}}',NULL,'2026-03-23 20:19:16','2026-04-13 21:10:32'),(179,74,'email',0,'{{manual_title}}','<!DOCTYPE html>\r\n<html lang=\"it\">\r\n<head><meta charset=\"UTF-8\"></head>\r\n<body style=\"margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#1e293b;\">\r\n<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"padding:32px 0;\">\r\n  <tr><td align=\"center\">\r\n    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\"\r\n           style=\"background:#ffffff;border-radius:8px;overflow:hidden;\r\n                  border:1px solid #e2e8f0;max-width:600px;\">\r\n      <tr>\r\n        <td style=\"background:#0891b2;padding:28px 32px;text-align:center;\">\r\n          <div style=\"font-size:42px;line-height:1;\">📢</div>\r\n          <div style=\"margin-top:12px;color:#ffffff;font-size:21px;font-weight:700;\r\n                      line-height:1.4;\">{{manual_title}}</div>\r\n        </td>\r\n      </tr>\r\n      <tr>\r\n        <td style=\"padding:32px 40px;\">\r\n          <p style=\"margin:0;font-size:15px;line-height:1.7;color:#374151;\">{{manual_body}}</p>\r\n        <p style=\"text-align:center;margin:28px 0 0;\">\r\n          <a href=\"{{link}}\"\r\n             style=\"display:inline-block;background:#0891b2;color:#ffffff;\r\n                    text-decoration:none;padding:12px 28px;border-radius:6px;\r\n                    font-weight:700;font-size:15px;\">Apri in Favilla &#8594;</a>\r\n        </p>\r\n        </td>\r\n      </tr>\r\n      <tr>\r\n        <td style=\"background:#f8fafc;padding:18px 40px;text-align:center;\r\n                   font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;\">\r\n          Favilla Intranet &mdash; Notifica automatica. Non rispondere a questa email.\r\n        </td>\r\n      </tr>\r\n    </table>\r\n  </td></tr>\r\n</table>\r\n</body></html>',NULL,'2026-03-23 20:19:16','2026-04-13 21:10:32'),(180,74,'telegram',0,'{{manual_title}}','{{manual_body}}\n\n{{link}}',NULL,'2026-03-23 20:19:16','2026-04-13 21:10:32'),(184,76,'in_app',1,'{{title}}','{{body}}',NULL,'2026-03-24 13:03:31','2026-04-13 21:10:32'),(185,76,'email',0,'{{title}}','<!DOCTYPE html>\n<html lang=\"it\">\n<head><meta charset=\"UTF-8\"></head>\n<body style=\"margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#1e293b;\">\n<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"padding:32px 0;\">\n  <tr><td align=\"center\">\n    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\"\n           style=\"background:#ffffff;border-radius:8px;overflow:hidden;\n                  border:1px solid #e2e8f0;max-width:600px;\">\n      <tr>\n        <td style=\"background:#64748b;padding:28px 32px;text-align:center;\">\n          <div style=\"font-size:42px;line-height:1;\">ℹ️</div>\n          <div style=\"margin-top:12px;color:#ffffff;font-size:21px;font-weight:700;\n                      line-height:1.4;\">{{title}}</div>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"padding:32px 40px;\">\n          <p style=\"margin:0;font-size:15px;line-height:1.7;color:#374151;\">{{body}}</p>\n        <p style=\"text-align:center;margin:28px 0 0;\">\n          <a href=\"{{link}}\"\n             style=\"display:inline-block;background:#64748b;color:#ffffff;\n                    text-decoration:none;padding:12px 28px;border-radius:6px;\n                    font-weight:700;font-size:15px;\">Apri in Favilla &#8594;</a>\n        </p>\n        </td>\n      </tr>\n      <tr>\n        <td style=\"background:#f8fafc;padding:18px 40px;text-align:center;\n                   font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;\">\n          Favilla Intranet &mdash; Notifica automatica. Non rispondere a questa email.\n        </td>\n      </tr>\n    </table>\n  </td></tr>\n</table>\n</body></html>',NULL,'2026-03-24 13:03:31','2026-04-13 21:10:32'),(186,76,'telegram',0,'{{title}}','{{body}}\n\n{{link}}',NULL,'2026-03-24 13:03:31','2026-04-13 21:10:32'),(190,78,'in_app',1,'Nuova registrazione: {{name}} ({{username}})','L\'utente {{name}} ({{username}} — {{email}}) si è registrato e attende approvazione.\nApri: {{link}}',NULL,'2026-03-25 13:06:54','2026-04-13 21:10:32'),(191,78,'email',0,NULL,NULL,NULL,'2026-03-25 13:06:54','2026-04-13 21:10:32'),(192,78,'telegram',0,NULL,NULL,NULL,'2026-03-25 13:06:54','2026-04-13 21:10:32'),(208,84,'in_app',1,'Promemoria: {{event_title}}','L\'evento inizia tra {{minutes_before}} minuti',NULL,'2026-03-29 18:37:26','2026-04-13 21:10:32'),(209,84,'email',0,'Promemoria: {{event_title}}','<h2>Promemoria evento</h2><p>{{event_title}}</p><p>Quando: {{start_label}}</p><p><a href=\"{{link}}\">Apri calendario</a></p>',NULL,'2026-03-29 18:37:26','2026-04-13 21:10:32'),(210,84,'telegram',0,NULL,'🔔 Promemoria: {{event_title}}\nTra {{minutes_before}} min — {{start_label}}\n{{link}}',NULL,'2026-03-29 18:37:26','2026-04-13 21:10:32'),(211,85,'in_app',1,'Allarme sicurezza: {{incident_title}}','Rilevato: {{incident_title}}. IP: {{ip}}. Dettagli nel pannello sicurezza.',NULL,'2026-04-01 19:50:11','2026-04-01 19:50:11'),(212,85,'email',1,'Allarme sicurezza Favilla: {{incident_title}}','<h2>Incidente di sicurezza rilevato</h2><p><strong>Tipo:</strong> {{incident_title}}</p><p><strong>IP:</strong> {{ip}}</p><p><a href=\"{{link}}\">Visualizza dettagli</a></p>',NULL,'2026-04-01 19:50:11','2026-04-01 19:50:11');


-- ------------------------------------------------------------------
-- 11. Job scheduler
-- ------------------------------------------------------------------

INSERT IGNORE INTO `scheduler_jobs` (`slug`, `name`, `command`, `args_json`, `interval_minutes`, `enabled`) VALUES
    ('notifications.process_queue', 'Processa coda notifiche',               'notifications:process-queue', '[\"--limit=50\"]', 5,     1),
    ('calendar.send_reminders',   'Promemoria eventi calendario',           'calendar:send-reminders',   NULL,              15,    1),
    ('contacts.process_reminders',  'Ricorrenze contatti (compleanni, anniversari)', 'contacts:process-reminders', NULL,          1440,  1),
    ('tasks.send_due_reminders', 'Scadenze attività personali',            'tasks:send-due-reminders', NULL,              1440,  1),
    ('cleanup',                     'Pulizia dati stale (sessioni, token, notifiche vecchie)', 'cleanup',    '[\"--days=30\"]',  10080, 1),
    ('backup.run',                  'Backup automatico database',             'backup:run',                  NULL,              1440,  1),
    ('logs.rotate',                 'Rotazione e pulizia file di log',        'logs:rotate',                 NULL,              10080, 1),
    ('retention.run',               'Policy data retention (ISO 27001)',      'retention:run',               NULL,              1440,  1),
    ('reports.cleanup',             'Pulizia report scaduti',                 'reports:cleanup',             NULL,              1440,  1),
    ('session.gc',                  'Garbage collection sessioni DB',         'session:gc',                  NULL,              60,    1),
    ('ratelimit.cleanup',           'Pulizia entry rate limit scadute',       'ratelimit:cleanup',           NULL,              1440,  1);


-- ------------------------------------------------------------------
-- 13. Policy data retention
-- ------------------------------------------------------------------

INSERT IGNORE INTO `data_retention_policies` (`id`, `entity`, `description`, `table_name`, `date_column`, `retention_days`, `action`, `anonymize_fields`, `enabled`, `last_run_at`, `created_at`, `updated_at`) VALUES (1,'Tentativi Login','Storico tentativi di accesso','login_attempts','created_at',90,'delete',NULL,1,NULL,'2026-04-01 20:26:40','2026-04-01 20:26:40'),(2,'Sessioni Scadute','Sessioni utente scadute','sessions','expires_at',0,'delete',NULL,1,NULL,'2026-04-01 20:26:40','2026-04-01 20:26:40'),(3,'Log Audit','Log audit completi','audit_logs','created_at',365,'delete',NULL,1,NULL,'2026-04-01 20:26:40','2026-04-01 20:26:40'),(4,'Incidenti Sicurezza','Log incidenti di sicurezza','security_incidents','created_at',730,'delete',NULL,0,NULL,'2026-04-01 20:26:40','2026-04-01 20:26:40'),(5,'Notifiche Lette','Notifiche utenti soft-deleted','notifications','deleted_at',90,'delete',NULL,1,NULL,'2026-04-01 20:26:40','2026-04-01 20:26:40'),(6,'Storico Report','File report generati scaduti','report_history','expires_at',0,'delete',NULL,1,NULL,'2026-04-01 20:26:40','2026-04-01 20:26:40'),(7,'Log Scheduler','Log esecuzioni scheduler','scheduler_log','started_at',60,'delete',NULL,1,NULL,'2026-04-01 20:26:40','2026-04-01 20:29:48'),(9,'Rate Limits','Record rate limiting temporanei','rate_limits','created_at',1,'delete',NULL,1,'2026-04-09 11:34:36','2026-04-01 20:26:40','2026-04-09 11:34:36');

-- Segnalazioni: anonimizza il contesto tecnico pesante dopo 365 giorni
INSERT IGNORE INTO `data_retention_policies`
    (`entity`, `description`, `table_name`, `date_column`, `retention_days`, `action`, `anonymize_fields`, `enabled`)
VALUES
    ('Segnalazioni — contesto tecnico',
     'Anonimizza il contesto tecnico (DOM, JSON, errori, user agent, URL) delle segnalazioni oltre il periodo di retention. Mantiene descrizione e stato.',
     'feedback', 'created_at', 365, 'anonymize',
     '["contesto_json","dom_snapshot","errori_console_json","user_agent","pagina_url"]', 1);



-- ------------------------------------------------------------------
-- 14. Changelog storico pubblicato
-- ------------------------------------------------------------------

INSERT IGNORE INTO `changelogs` (`version`, `title`, `notes`, `release_date`, `is_published`, `created_by`, `created_at`, `updated_at`) VALUES
    ('0.1.0', 'Prime basi di Favilla', CONCAT_WS(CHAR(10),
        '- Nascita del progetto con la struttura iniziale della piattaforma',
        '- Prime schermate condivise e organizzazione dell ambiente di lavoro',
        '- Fondamenta per moduli, configurazioni e area pubblica'
    ), '2025-12-27', 1, 1, '2025-12-27 09:00:00', '2025-12-27 09:00:00'),
    ('0.2.0', 'Navigazione e percorsi principali', CONCAT_WS(CHAR(10),
        '- Navigazione base tra le sezioni principali',
        '- Accessi, reindirizzamenti e ritorni alle schermate piu affidabili',
        '- Gestione centralizzata delle sezioni dell applicazione'
    ), '2025-12-30', 1, 1, '2025-12-30 09:00:00', '2025-12-30 09:00:00'),
    ('0.3.0', 'Salvataggi e controlli dati', CONCAT_WS(CHAR(10),
        '- Salvataggi, modifiche e cancellazioni dei dati principali piu stabili',
        '- Controlli sui form e messaggi di errore piu chiari',
        '- Processi a piu passaggi piu affidabili'
    ), '2026-01-03', 1, 1, '2026-01-03 09:00:00', '2026-01-03 09:00:00'),
    ('0.4.0', 'Accesso utenti', CONCAT_WS(CHAR(10),
        '- Login, logout e recupero password',
        '- Prime sezioni protette in base al profilo utente',
        '- Tracciatura degli accessi principali'
    ), '2026-01-07', 1, 1, '2026-01-07 09:00:00', '2026-01-07 09:00:00'),
    ('0.5.0', 'Area amministrativa iniziale', CONCAT_WS(CHAR(10),
        '- Area Admin per utenti, ruoli e permessi',
        '- Impostazioni globali, template email e storico comunicazioni',
        '- Tracciamento delle versioni del prodotto'
    ), '2026-01-11', 1, 1, '2026-01-11 09:00:00', '2026-01-11 09:00:00'),
    ('0.6.3', 'Notifiche interne', CONCAT_WS(CHAR(10),
        '- Prime notifiche visibili dentro Favilla',
        '- Contatore di lettura e gestione dello stato dei messaggi',
        '- Base pronta per i canali multipli'
    ), '2026-01-15', 1, 1, '2026-01-15 09:00:00', '2026-01-15 09:00:00'),
    ('0.7.0', 'Archivio file iniziale', CONCAT_WS(CHAR(10),
        '- Modulo Files per caricare e organizzare documenti',
        '- Controlli su tipo file e accesso ai download',
        '- Cartelle logiche e gestione dei documenti personali'
    ), '2026-01-19', 1, 1, '2026-01-19 09:00:00', '2026-01-19 09:00:00'),
    ('0.8.0', 'Impostazioni e comunicazioni email', CONCAT_WS(CHAR(10),
        '- Impostazioni principali della piattaforma centralizzate',
        '- Email di servizio e template piu affidabili',
        '- Storico degli invii per i controlli amministrativi'
    ), '2026-01-24', 1, 1, '2026-01-24 09:00:00', '2026-01-24 09:00:00'),
    ('0.9.0', 'Recupero dati e stabilita', CONCAT_WS(CHAR(10),
        '- Recupero degli elementi eliminati quando previsto',
        '- Maggiore continuita dei dati nelle liste e nelle operazioni quotidiane',
        '- Processi di pulizia e recupero piu sicuri'
    ), '2026-01-29', 1, 1, '2026-01-29 09:00:00', '2026-01-29 09:00:00'),
    ('1.0.0', 'Piattaforma pronta a crescere', CONCAT_WS(CHAR(10),
        '- Base comune per moduli, dashboard e strumenti completata',
        '- Schermate, componenti condivisi e comportamento uniformati',
        '- Esperienza piu coerente tra area utente e area admin'
    ), '2026-02-02', 1, 1, '2026-02-02 09:00:00', '2026-02-02 09:00:00'),
    ('1.1.0', 'Home, dashboard e widget', CONCAT_WS(CHAR(10),
        '- Home come cruscotto applicativo con quick access e riepiloghi',
        '- Sistema widget con ordine personalizzabile per utente',
        '- Statistiche contestuali e scorciatoie operative'
    ), '2026-02-06', 1, 1, '2026-02-06 09:00:00', '2026-02-06 09:00:00'),
    ('1.2.0', 'Notifiche multicanale', CONCAT_WS(CHAR(10),
        '- Centro avvisi con invii in app, email e Telegram',
        '- Preferenze personali per canale e tipo di evento',
        '- Invii manuali amministrativi e riepilogo consegne'
    ), '2026-02-10', 1, 1, '2026-02-10 09:00:00', '2026-02-10 09:00:00'),
    ('1.3.0', 'Backup, privacy e continuita operativa', CONCAT_WS(CHAR(10),
        '- Modulo Backup con archivi automatici e rotazione dei salvataggi',
        '- Consensi privacy e prime regole di conservazione dati',
        '- Notifiche di completamento e ripristino guidato'
    ), '2026-02-14', 1, 1, '2026-02-14 09:00:00', '2026-02-14 09:00:00'),
    ('1.4.4', 'Personalizzazione utente', CONCAT_WS(CHAR(10),
        '- Preferenze persistenti per aspetto e resa grafica',
        '- Pagina profilo e memorizzazione opzioni personali migliorate',
        '- Base pronta per skin, font e stili di navigazione'
    ), '2026-02-18', 1, 1, '2026-02-18 09:00:00', '2026-02-18 09:00:00'),
    ('1.5.0', 'Piattaforma pronta per i moduli', CONCAT_WS(CHAR(10),
        '- Struttura dati delle funzioni applicative razionalizzata',
        '- Gestione dei moduli e catalogo funzionalita piu affidabili',
        '- Stabilita generale migliorata in vista della suite completa'
    ), '2026-02-22', 1, 1, '2026-02-22 09:00:00', '2026-02-22 09:00:00'),
    ('1.6.0', 'Modulo Attivita', CONCAT_WS(CHAR(10),
        '- Modulo Attivita con elenco personale, vista board e azioni rapide',
        '- Checklist, tag, scadenze e promemoria collegati alla dashboard',
        '- Notifiche per attivita in scadenza, export ed integrazione col calendario'
    ), '2026-02-26', 1, 1, '2026-02-26 09:00:00', '2026-02-26 09:00:00'),
    ('1.7.0', 'Modulo Calendario', CONCAT_WS(CHAR(10),
        '- Modulo Calendario con viste giornaliera, settimanale e mensile',
        '- Eventi personali e condivisi con luogo, orario e descrizione',
        '- Reminder evento, widget Home e condivisione per ruolo'
    ), '2026-03-01', 1, 1, '2026-03-01 09:00:00', '2026-03-01 09:00:00'),
    ('1.8.0', 'Modulo Contatti', CONCAT_WS(CHAR(10),
        '- Modulo Contatti con rubrica personale, categorie e campi avanzati',
        '- Ricorrenze, compleanni e reminder automatici',
        '- Import/export, ricerca, viste filtrate e condivisione per ruolo'
    ), '2026-03-05', 1, 1, '2026-03-05 09:00:00', '2026-03-05 09:00:00'),
    ('1.9.6', 'Reports ed export', CONCAT_WS(CHAR(10),
        '- Modulo Reports con esportazioni CSV, Excel e PDF',
        '- Template report, preset di stile e storico delle generazioni',
        '- Area export centralizzata collegata ai moduli'
    ), '2026-03-09', 1, 1, '2026-03-09 09:00:00', '2026-03-09 09:00:00'),
    ('1.10.0', 'Designer dei report', CONCAT_WS(CHAR(10),
        '- Designer visuale per report personalizzati',
        '- Anteprime, layout configurabili e placeholder dinamici',
        '- Template riusabili e cronologia export piu leggibile'
    ), '2026-03-12', 1, 1, '2026-03-12 09:00:00', '2026-03-12 09:00:00'),
    ('1.11.0', 'Automazioni e promemoria', CONCAT_WS(CHAR(10),
        '- Motore di automazione per attivita periodiche e routine',
        '- Promemoria automatici per Attivita, Calendario e Contatti',
        '- Backup pianificati, pulizie ricorrenti e log delle esecuzioni'
    ), '2026-03-16', 1, 1, '2026-03-16 09:00:00', '2026-03-16 09:00:00'),
    ('1.12.3', 'HealthCheck e monitoraggio', CONCAT_WS(CHAR(10),
        '- Modulo HealthCheck per salute e prontezza del sistema',
        '- Storico esecuzioni, metriche di base, export CSV e notifiche di errore',
        '- Controllo rapido dello stato ambiente per setup e runtime'
    ), '2026-03-20', 1, 1, '2026-03-20 09:00:00', '2026-03-20 09:00:00'),
    ('1.13.0', 'Sicurezza avanzata e doppio fattore', CONCAT_WS(CHAR(10),
        '- Autenticazione a due fattori con codici di emergenza',
        '- Strumenti per incidenti, inventario asset e avvisi di sicurezza',
        '- Audit e protezione degli accessi sensibili rafforzati'
    ), '2026-03-24', 1, 1, '2026-03-24 09:00:00', '2026-03-24 09:00:00'),
    ('1.14.0', 'Accesso avanzato e sessioni protette', CONCAT_WS(CHAR(10),
        '- Gestione delle sessioni e degli accessi concorrenti piu solida',
        '- Registrazione utente, approvazione admin e reset password rafforzati',
        '- Policy password con complessita, scadenza e storico riutilizzo'
    ), '2026-03-28', 1, 1, '2026-03-28 09:00:00', '2026-03-28 09:00:00'),
    ('1.15.0', 'Files condivisi e strumenti admin', CONCAT_WS(CHAR(10),
        '- Files con condivisione per ruolo, cestino e recupero documenti',
        '- Ripristino backup e viste amministrative dedicate',
        '- Gestione versioni, storico e accesso protetto ai download'
    ), '2026-04-01', 1, 1, '2026-04-01 09:00:00', '2026-04-01 09:00:00'),
    ('1.16.5', 'Tema avanzato e controllo operativo', CONCAT_WS(CHAR(10),
        '- Personalizzazione con skin, famiglie font e stili sidebar',
        '- Policy di conservazione dati e dashboard sicurezza piu ricche',
        '- Dark mode e coerenza visiva tra i moduli rifinite'
    ), '2026-04-13', 1, 1, '2026-04-13 09:00:00', '2026-04-13 09:00:00'),
    ('1.18.2', 'Consolidamento Favilla', CONCAT_WS(CHAR(10),
        '- Suite dei moduli e dei flussi operativi consolidata',
        '- Help online contestuale e changelog con timeline storica',
        '- Sicurezza, notifiche, report, file e automazioni in una piattaforma coerente'
    ), '2026-05-01', 1, 1, '2026-05-01 09:00:00', '2026-05-01 09:00:00'),
    ('2.0.0', 'Release Candidate 2.0', CONCAT_WS(CHAR(10),
        '- Suite completa consolidata verso Favilla 2.0',
        '- Pagina Oggi con timeline personale e priorita giornaliere',
        '- Interazioni utente, ricerca trasversale e pagine personali rifinite'
    ), '2026-05-16', 1, 1, '2026-05-16 09:00:00', '2026-05-16 09:00:00'),
    ('2.0.2', 'Modulo Help Online', CONCAT_WS(CHAR(10),
        '- Help Online con assistenza contestuale dentro le schermate',
        '- Knowledge base interrogabile con risposte guidate e argomenti rapidi',
        '- Pannello admin per moduli, alias, query utenti e analisi della copertura'
    ), '2026-05-20', 1, 1, '2026-05-20 09:00:00', '2026-05-20 09:00:00');


-- ------------------------------------------------------------------
-- 15. Moduli edizione Team (Progetti, Teams, Documenti, Blog)
-- ------------------------------------------------------------------
-- Disabilitati di default su ogni installazione fresca: restano installabili
-- manualmente da Admin -> Moduli (percorso di upgrade Personal/Developer ->
-- Team). SetupController::runSetupComplete() li riabilita automaticamente
-- solo quando l'operatore sceglie l'edizione "team" al passo 4 del wizard.

INSERT IGNORE INTO `module_states` (`name`, `enabled`) VALUES
    ('Progetti', 0),
    ('Teams', 0),
    ('Documenti', 0),
    ('Blog', 0);


-- ------------------------------------------------------------------
-- 16. Dati bundled moduli edizione Team (Blog, Documenti)
-- ------------------------------------------------------------------

-- Blog: setting moderazione commenti
INSERT IGNORE INTO `app_settings` (`key`, `value`, `type`, `group`, `label`)
VALUES ('blog.comment_moderation', '0', 'bool', 'blog',
        'Se attivo, i commenti delle Comunicazioni richiedono approvazione admin prima di essere visibili');

-- Blog: categoria predefinita
INSERT IGNORE INTO `blog_categories` (`name`, `slug`, `description`, `sort_order`, `created_by`)
    VALUES ('Generale', 'generale', 'Categoria predefinita', 0, NULL);

-- Blog: template bundled — Scheda Articolo PDF (Smart Components + {{ placeholder }})
INSERT INTO `report_templates` (
    `name`, `description`, `module`, `source_key`, `source_type`, `output_format`,
    `visibility`, `max_rows`, `template_html`, `bundled_module`, `created_by`
)
SELECT
    'Scheda Articolo',
    'Scheda PDF per un singolo articolo delle Comunicazioni con contenuto completo',
    'Blog', 'articles', 'document', 'pdf', 'global', 1,
    '<div data-prm-type="logo" data-prm-config=''{"max_height":50,"align":"left"}''></div><div style="font-size:9pt;color:#64748b;margin:4px 0;"><span data-prm-type="system" data-prm-config=''{"kind":"company"}''></span></div><hr style="border:none;border-top:1px solid #3b82f6;margin:6px 0 12px;"><div style="font-size:9pt;color:#3b82f6;font-weight:600;margin-bottom:2px;">{{ category_name }}</div><h1 style="color:#0f172a;font-size:20pt;margin:0 0 10px;">{{ title }}</h1><table style="width:100%;border-collapse:collapse;font-size:9pt;margin-bottom:12px;"><tr><td style="padding:4px 8px;border:1px solid #dee2e6;background:#f8fafc;font-weight:600;width:22%;">Autore</td><td style="padding:4px 8px;border:1px solid #dee2e6;width:28%;">{{ author_name }}</td><td style="padding:4px 8px;border:1px solid #dee2e6;background:#f8fafc;font-weight:600;width:22%;">Pubblicato il</td><td style="padding:4px 8px;border:1px solid #dee2e6;">{{ published_at }}</td></tr><tr><td style="padding:4px 8px;border:1px solid #dee2e6;background:#f8fafc;font-weight:600;">Min. lettura</td><td style="padding:4px 8px;border:1px solid #dee2e6;">{{ reading_time }}</td><td style="padding:4px 8px;border:1px solid #dee2e6;background:#f8fafc;font-weight:600;">Commenti</td><td style="padding:4px 8px;border:1px solid #dee2e6;">{{ comment_count }}</td></tr></table><hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 10px;"><p style="font-size:11pt;font-style:italic;color:#475569;margin:0 0 10px;">{{ excerpt }}</p><div style="font-size:11pt;color:#1e293b;white-space:pre-wrap;margin-bottom:12px;">{{ content_text }}</div><div style="font-size:9pt;color:#64748b;margin-bottom:10px;">Tag: {{ tags }}</div><hr style="border:none;border-top:1px solid #64748b;margin:12px 0 6px;"><div style="font-size:8pt;color:#64748b;">Generato il <span data-prm-type="system" data-prm-config=''{"kind":"datetime"}''></span> da <span data-prm-type="system" data-prm-config=''{"kind":"user"}''></span></div>',
    'Blog', NULL
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `report_templates` WHERE `name` = 'Scheda Articolo' AND `module` = 'Blog'
);

INSERT IGNORE INTO `document_bindings` (`module`, `operation`, `label`, `template_id`, `created_by`)
SELECT 'Blog', 'articles', 'Scheda Articolo', `id`, NULL
FROM `report_templates`
WHERE `name` = 'Scheda Articolo' AND `module` = 'Blog'
LIMIT 1;

-- Documenti: categoria predefinita (obbligatoria per creare documenti)
INSERT IGNORE INTO `documenti_categorie`
    (`nome`, `slug`, `codice`, `descrizione`, `path`, `depth`, `approvazione_richiesta`, `ordine`, `created_at`, `updated_at`)
VALUES
    ('Generale', 'generale', 'GEN', 'Categoria predefinita per i documenti', '/', 0, 1, 0, NOW(), NOW());

-- Allinea il path materializzato alla convenzione dell'app (/<id>/)
UPDATE `documenti_categorie`
   SET `path` = CONCAT('/', `id`, '/')
 WHERE `codice` = 'GEN' AND (`path` IS NULL OR `path` = '/');


SET FOREIGN_KEY_CHECKS = 1;
