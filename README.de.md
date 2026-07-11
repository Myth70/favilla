<div align="center">

<img width="313" height="234" alt="Favilla" src="https://github.com/user-attachments/assets/fc57ecac-ddfb-4c0f-8aaf-c1bff9b947b5" />

**Der selbstgehostete Workspace, der dein Unternehmen am Laufen hält — und dir gehört.**

Projekte · Dokumente · Team-Chat · Aufgaben · Kalender · Kontakte · Dateien · Berichte

[![CI](https://github.com/Myth70/favilla/actions/workflows/ci.yml/badge.svg)](https://github.com/Myth70/favilla/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/Myth70/favilla)](../../releases)
[![License: AGPL-3.0-or-later](https://img.shields.io/badge/license-AGPL--3.0--or--later-blue)](LICENSE)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](composer.json)

🌐 [English](README.md) · [Italiano](README.it.md) · [Français](README.fr.md) · **Deutsch** · [Español](README.es.md)

</div>

**Favilla** — italienisch für „Funke" — ist ein vollständiger Workspace und ein
Firmen-Intranet, das du selbst hostest: Projekte mit Gantt, Zeiterfassung und
Budgets, Dokumente mit Freigabe-Workflows, Team-Messaging, Kanban-Aufgaben,
geteilte Kalender, Kontakte, Dateien, druckfertige Berichte,
Multi-Channel-Benachrichtigungen und eine vollständige Sicherheits- und
Compliance-Suite. Achtzehn Module, fünf Sprachen, eine Installation, auf deinem
eigenen Server — keine Preise pro Platz, keine Telemetrie, nichts „telefoniert nach
Hause", und die AGPL sorgt dafür, dass das so bleibt.

Auf der Landkarte der Tools, die du bereits kennst, liegt Favilla dort, wo sich ein
Projekt-Tracker, ein Dokumentenmanagement-System und ein Team-Messenger
überschneiden — ein operatives Intranet in der Tradition von Basecamp, keine
Office-Suite. Es ergänzt Nextcloud, statt es zu ersetzen: Favilla synchronisiert
keine Dateien, es steuert deine Projekte, Dokumente und Prozesse.

**Was Favilla nicht ist:** eine Datei-Sync- oder Office-Suite (das ist Nextcloud /
OnlyOffice), ein öffentliches CMS, ein kundenseitiger Helpdesk oder ein
mandantenfähiges SaaS. Es ist ein interner operativer Workspace für eine einzelne
Organisation, betrieben von dieser Organisation auf ihrem eigenen Server.

![Favilla-Dashboard](docs/screenshots/dashboard.png)

## Was es anders macht

- **Berichte bauen wie Folien.** Ein Drag-and-drop-Designer (GrapesJS) für
  druckfertige PDF- und Excel-Vorlagen, direkt im Browser: intelligente
  Datenkomponenten, wiederverwendbare Stile, serverseitige Bereinigung. Berichte
  sind erstklassige Bürger, kein nachträglicher Einfall.
- **Hilfe, die mit dem Produkt kommt.** Jede Seite hat ein kontextbezogenes
  Hilfe-Panel, gestützt auf eine integrierte Wissensdatenbank — über 340 Fragen
  und Antworten, jede in allen fünf Sprachen, mit synonymbewusster Suche und
  Admin-Analysen dazu, wonach Nutzer suchen und was sie nicht finden. Weniger „Wie
  mache ich…?"-Tickets ab dem ersten Tag.
- **Eine Sicherheits-Suite, wie man sie von kostenpflichtiger Software erwartet.**
  SSO (OIDC) mit PKCE, Kontoverknüpfung und optionalem JIT-Provisioning;
  TOTP-Zwei-Faktor-Authentifizierung; ein Sicherheits-Dashboard mit
  Vorfallerkennung (Brute Force, CSRF); vollständiges Audit-Log;
  Datenaufbewahrungsrichtlinien; AES-256-GCM-verschlüsselte Backups mit
  Wiederherstellung in der App; Session-Härtung, Login-Ratenbegrenzung,
  Passwortrichtlinie.
- **Fünf Sprachen von Haus aus.** Italienisch (die kanonische Quelle), Englisch,
  Französisch, Deutsch und Spanisch, mit einem Umschalter pro Nutzer — und nicht
  nur die Oberfläche: Auch die Benachrichtigungen und die Hilfe-Wissensdatenbank
  sind übersetzt. (Code und Dokumentation sind auf Englisch.)
- **Ein Codebestand, drei Editionen.** Personal, Team und Developer sind dasselbe
  Produkt in anderen Kleidern: Fang allein an und wachse zum Firmen-Intranet, ohne
  etwas neu zu installieren. Siehe [Editionen](#editionen).
- **Bereit für KI-Assistenten.** Das Repository liefert [`CLAUDE.md`](CLAUDE.md),
  maschinenlesbare Modul-Inventare (`project_context.json`, `context/`) und
  schriftliche Architekturverträge (`docs/contracts/`), sodass Coding-Agenten und
  neue Mitwirkende es auf gleiche Weise durchsuchen. Ein Großteil von Favilla
  entstand in Paararbeit mit KI-Agenten — dieser Workflow ist erstklassig, nicht
  beiläufig.

Und die Grundlagen sind alle vorhanden:

- **Ein Dashboard, das wirklich dir gehört** — 17 Live-Widget-Anbieter (die
  heutige Agenda, offene Aufgaben, Projektstatus, Backup-Zustand… sogar das lokale
  Wetter); jeder Nutzer wählt, verbirgt und ordnet seine eigenen.
- **Vorlagengesteuerte Benachrichtigungen** — ein Dispatcher, drei Kanäle (in-app,
  E-Mail, Telegram), Einstellungen pro Nutzer, Zustellung in der Warteschlange mit
  Retry/Backoff; Admins steuern Wortlaut und Aussehen über die Oberfläche.
- **Schnell zu navigieren** — globale Suche über alle Module, ein radiales
  Schnellmenü per Rechtsklick, HTMX-Teilaktualisierungen überall, helle und dunkle
  Themes.
- **Betrieb eingebaut** — ein cron-äquivalenter Scheduler mit Admin-Oberfläche,
  Health-Checks mit Verlauf und Export, Log-Rotation und eine Projekt-CLI
  (`php favilla`) für die Automatisierung.

## Langweilige Technik, gebaut um zu bleiben

Favilla trifft zwei bewusst unmoderne Entscheidungen:

1. **Serverseitig gerendertes PHP 8.2 + HTMX.** Keine SPA, kein Build-Schritt, kein
   `node_modules`. Es läuft auf allem, von XAMPP bis Docker Compose, und gedeiht
   auf einem Raspberry Pi.
2. **Ein eigenes Micro-Framework — kein Laravel, kein Symfony.** Eine klassische
   MVC-Anwendung, die du von Anfang bis Ende lesen, prüfen und erweitern kannst:
   Controller, Services, Repositories, Views, keine Magie.

Solche Entscheidungen halten nur mit Disziplin dahinter: **über 1.800
automatisierte Tests**, **PHPStan Level 6** und **PSR-12**, in der CI erzwungen,
und ein **Schema mit über 100 Tabellen**, installiert von einem geführten
Einrichtungsassistenten.

## Screenshots

| | |
|---|---|
| ![Konfigurierbares Dashboard](docs/screenshots/dashboard-configure.png) <br>*Jedes Widget gehört dir: ziehen zum Umordnen, tippen zum Ausblenden* | ![Kontextbezogene Hilfe](docs/screenshots/help-online.png) <br>*Kontextbezogene Hilfe mit durchsuchbarer Wissensdatenbank, auf jeder Seite* |
| ![Kanban-Board](docs/screenshots/tasks-kanban.png) <br>*Aufgaben als Liste, Kalender oder Kanban-Board* | ![Darstellungseinstellungen](docs/screenshots/appearance.png) <br>*Themes, Farben, Schriften und Layout-Stile pro Nutzer* |

## Editionen

Ein Produkt, das mit dir wächst. Favilla entsteht aus einem einzigen Codebestand in
drei Editionen, gewählt im Einrichtungsassistenten (oder später geändert unter
Admin → Konfiguration):

- **Personal** — ein Einzelnutzer-Workspace. Die Registrierung ist aus, und jede
  Mehrbenutzer-Fläche (Rollen, Freigabe, Admin-Bereich) ist in einer diskreten
  Einstellungen-Ecke verstaut. Es fühlt sich wie eine persönliche App an; darunter
  ist es trotzdem ganz Favilla.
- **Team** — das Mehrbenutzer-Firmen-Intranet: rollenbasierte Berechtigungen,
  offene Registrierung mit Admin-Freigabe und Projekte, Teams, Dokumente und Blog
  standardmäßig aktiviert.
- **Developer** — um an Favilla selbst zu arbeiten: das vollständige Repository,
  einschließlich der Dokumentation für Mitwirkende und KI-Assistenten
  (`CLAUDE.md`, `docs/contracts/`, `context/`).

| | **Personal** | **Team** | **Developer** |
|---|---|---|---|
| Gedacht für | Persönlicher Einzelnutzer-Workspace | Mehrbenutzer-Firmen-Intranet | Zu Favilla selbst beitragen |
| Mehrbenutzer-/RBAC-Oberfläche | Verborgen | Sichtbar | Sichtbar |
| Registrierungsseite | Deaktiviert (einzelnes Konto) | Offen | Offen |
| Projekte, Teams, Dokumente, Blog | Installierbar über Admin → Module | **Standardmäßig aktiviert** | Installierbar über Admin → Module |
| Dev- und KI-Dokumentation | Nicht enthalten | Nicht enthalten | Enthalten |

Eine Edition ändert, was die Oberfläche zeigt — nie, was der Code kann.
**Verborgen ≠ deaktiviert:** Der Scheduler und alle Kernmodule laufen in jeder
Edition, sodass nichts, worauf andere Funktionen (etwa Erinnerungen) angewiesen
sind, je verschwindet. Wenn eine Personal-Installation nicht mehr nur dir gehört,
aktiviere die vier Team-Module über **Admin → Module** und wechsle die Edition
unter **Admin → Konfiguration** — keine Neuinstallation, keine Migration, kein
Export/Import.

## Installation und vollständige Dokumentation

Die Installation mit Docker oder XAMPP, die Voraussetzungen, das Upgrade, die
vollständige Funktionsliste Modul für Modul und die Entwicklerdokumentation stehen
im **[englischen README](README.md)** und in **[FEATURES.md](FEATURES.md)**.

## Lizenz

Favilla steht unter der **GNU Affero General Public License v3.0 oder höher
(AGPL-3.0-or-later)**. Kurz gesagt: Wenn du eine veränderte Version von Favilla als
Netzwerkdienst betreibst, musst du deren geänderten Quellcode ihren Nutzern
zugänglich machen. Vollständiger Text in [`LICENSE`](LICENSE).

<div align="center">
    <img width="300" height="100" alt="mobile-title" src="https://github.com/user-attachments/assets/ceeff067-98e1-4f7c-bb19-9585e501c275" />

<sub>Made in Italy 🇮🇹</sub>
</div>
