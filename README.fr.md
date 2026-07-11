<div align="center">

<img width="313" height="234" alt="Favilla" src="https://github.com/user-attachments/assets/fc57ecac-ddfb-4c0f-8aaf-c1bff9b947b5" />

**L'espace de travail auto-hébergé qui fait tourner votre entreprise — et qui reste le vôtre.**

Projets · Documents · Messagerie d'équipe · Tâches · Agenda · Contacts · Fichiers · Rapports

[![CI](https://github.com/Myth70/favilla/actions/workflows/ci.yml/badge.svg)](https://github.com/Myth70/favilla/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/Myth70/favilla)](../../releases)
[![License: AGPL-3.0-or-later](https://img.shields.io/badge/license-AGPL--3.0--or--later-blue)](LICENSE)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](composer.json)

🌐 [English](README.md) · [Italiano](README.it.md) · **Français** · [Deutsch](README.de.md) · [Español](README.es.md)

</div>

**Favilla** — « étincelle » en italien — est un espace de travail complet et un
intranet d'entreprise que vous hébergez vous-même : projets avec Gantt, feuilles de
temps et budgets, documents avec circuits de validation, messagerie d'équipe,
tâches kanban, agendas partagés, contacts, fichiers, rapports prêts à imprimer,
notifications multicanal et une suite complète de sécurité et de conformité.
Dix-huit modules, cinq langues, une seule installation, sur votre propre serveur —
pas de tarification par utilisateur, pas de télémétrie, rien qui « téléphone à la
maison », et la licence AGPL garantit qu'il en reste ainsi.

Sur la carte des outils que vous connaissez déjà, Favilla se situe à l'intersection
d'un gestionnaire de projets, d'un système de gestion documentaire et d'une
messagerie d'équipe — un intranet opérationnel dans la tradition de Basecamp, pas
une suite bureautique. Il complète Nextcloud plutôt qu'il ne le remplace : Favilla
ne synchronise pas les fichiers, il fait tourner vos projets, documents et
processus.

**Ce que Favilla n'est pas :** une suite de synchronisation de fichiers ou
bureautique (c'est Nextcloud / OnlyOffice), un CMS public, un support client, ni un
SaaS multi-locataire. C'est un espace de travail opérationnel interne pour une
seule organisation, gérée par cette organisation sur son propre serveur.

![Tableau de bord de Favilla](docs/screenshots/dashboard.png)

## Ce qui le rend différent

- **Construisez des rapports comme des diapositives.** Un concepteur
  glisser-déposer (GrapesJS) pour des modèles PDF et Excel prêts à imprimer,
  directement dans le navigateur : composants de données intelligents, styles
  réutilisables, assainissement côté serveur. Les rapports sont des citoyens de
  première classe, pas une arrière-pensée.
- **Une aide livrée avec le produit.** Chaque page dispose d'un panneau d'aide
  contextuelle adossé à une base de connaissances intégrée — plus de 340
  questions-réponses, chacune dans les cinq langues, avec une recherche sensible
  aux synonymes et des statistiques d'administration sur ce que les utilisateurs
  cherchent et ne trouvent pas. Moins de tickets « comment faire… ? » dès le
  premier jour.
- **Une suite de sécurité digne d'un logiciel payant.** SSO (OIDC) avec PKCE,
  liaison de comptes et provisionnement JIT optionnel ; authentification à deux
  facteurs TOTP ; un tableau de bord de sécurité avec détection d'incidents (force
  brute, CSRF) ; journal d'audit complet ; politiques de rétention des données ;
  sauvegardes chiffrées AES-256-GCM avec restauration depuis l'application ;
  renforcement des sessions, limitation du débit de connexion, politique de mots de
  passe.
- **Cinq langues prêtes à l'emploi.** Italien (la source canonique), anglais,
  français, allemand et espagnol, avec un sélecteur par utilisateur — et pas
  seulement l'interface : les notifications et la base de connaissances de l'aide
  sont également traduites. (Le code et la documentation sont en anglais.)
- **Un seul code, trois éditions.** Personal, Team et Developer sont le même
  produit sous des habits différents : commencez seul, évoluez vers un intranet
  d'entreprise sans rien réinstaller. Voir les [Éditions](#éditions).
- **Prêt pour les assistants IA.** Le dépôt fournit [`CLAUDE.md`](CLAUDE.md), des
  inventaires de modules lisibles par machine (`project_context.json`, `context/`)
  et des contrats d'architecture écrits (`docs/contracts/`), afin que les agents de
  codage et les nouveaux contributeurs naviguent de la même manière. Une grande
  partie de Favilla a été construite en binôme avec des agents IA — ce flux de
  travail est de première classe, pas accessoire.

Et les fondamentaux sont tous là :

- **Un tableau de bord vraiment à vous** — 17 fournisseurs de widgets en direct
  (l'agenda du jour, les tâches ouvertes, l'état des projets, la santé des
  sauvegardes… même la météo locale) ; chaque utilisateur choisit, masque et
  réorganise les siens.
- **Des notifications pilotées par modèles** — un seul répartiteur, trois canaux
  (in-app, e-mail, Telegram), des préférences par utilisateur, une livraison en
  file d'attente avec retry/backoff ; les administrateurs contrôlent le texte et
  l'apparence depuis l'interface.
- **Rapide à parcourir** — recherche globale sur tous les modules, un menu radial
  rapide au clic droit, des mises à jour partielles HTMX partout, thèmes clair et
  sombre.
- **L'exploitation intégrée** — un planificateur équivalent à cron avec interface
  d'administration, des contrôles de santé avec historique et export, la rotation
  des journaux, et une CLI de projet (`php favilla`) pour l'automatisation.

## Une technologie ennuyeuse, faite pour durer

Favilla fait deux choix délibérément démodés :

1. **PHP 8.2 + HTMX rendus côté serveur.** Pas de SPA, pas d'étape de build, pas de
   `node_modules`. Il se déploie sur tout, de XAMPP à Docker Compose, et tourne
   sans peine sur un Raspberry Pi.
2. **Un micro-framework maison — pas de Laravel, pas de Symfony.** Une application
   MVC classique que vous pouvez lire, auditer et étendre de bout en bout :
   contrôleurs, services, dépôts, vues, aucune magie.

Des choix pareils ne tiennent qu'avec de la discipline derrière : **plus de 1 800
tests automatisés**, **PHPStan niveau 6** et **PSR-12** appliqués en CI, et un
**schéma de plus de 100 tables** installé par un assistant guidé.

## Captures d'écran

| | |
|---|---|
| ![Tableau de bord configurable](docs/screenshots/dashboard-configure.png) <br>*Chaque widget est à vous : glissez pour réorganiser, touchez pour masquer* | ![Aide contextuelle](docs/screenshots/help-online.png) <br>*Aide contextuelle avec une base de connaissances cherchable, sur chaque page* |
| ![Tableau kanban](docs/screenshots/tasks-kanban.png) <br>*Les tâches en liste, agenda ou tableau kanban* | ![Paramètres d'apparence](docs/screenshots/appearance.png) <br>*Thèmes, couleurs, polices et styles de mise en page par utilisateur* |

## Éditions

Un produit qui grandit avec vous. Favilla provient d'un code unique en trois
éditions, choisies lors de l'assistant d'installation (ou modifiées ensuite depuis
Admin → Configuration) :

- **Personal** — un espace de travail mono-utilisateur. L'inscription est
  désactivée et chaque surface multi-utilisateur (rôles, partage, espace
  d'administration) est rangée dans un discret coin Paramètres. Cela ressemble à
  une application personnelle ; c'est pourtant toute Favilla en dessous.
- **Team** — l'intranet d'entreprise multi-utilisateur : permissions basées sur
  les rôles, inscription ouverte avec approbation de l'administrateur, et Projets,
  Teams, Documents et Blog activés par défaut.
- **Developer** — pour travailler sur Favilla elle-même : le dépôt complet, y
  compris la documentation pour contributeurs et assistants IA (`CLAUDE.md`,
  `docs/contracts/`, `context/`).

| | **Personal** | **Team** | **Developer** |
|---|---|---|---|
| Destinée à | Espace de travail personnel mono-utilisateur | Intranet d'entreprise multi-utilisateur | Contribuer à Favilla elle-même |
| Interface multi-utilisateur / RBAC | Masquée | Visible | Visible |
| Page d'inscription | Désactivée (compte unique) | Ouverte | Ouverte |
| Projets, Teams, Documents, Blog | Installables depuis Admin → Modules | **Activés par défaut** | Installables depuis Admin → Modules |
| Documentation dev et IA | Non incluse | Non incluse | Incluse |

Une édition change ce que l'interface montre — jamais ce que le code peut faire.
**Masqué ≠ désactivé :** le planificateur et tous les modules de base fonctionnent
dans chaque édition, si bien que rien dont d'autres fonctions dépendent (comme les
rappels) ne disparaît jamais. Lorsqu'une installation Personal cesse d'être
seulement la vôtre, activez les quatre modules d'équipe depuis **Admin → Modules**
et changez d'édition dans **Admin → Configuration** — aucune réinstallation, aucune
migration, aucun export/import.

## Installation et documentation complète

L'installation avec Docker ou XAMPP, les prérequis, la mise à niveau, la liste
complète des fonctionnalités module par module et la documentation pour
développeurs se trouvent dans le **[README en anglais](README.md)** et dans
**[FEATURES.md](FEATURES.md)**.

## Licence

Favilla est distribué sous **GNU Affero General Public License v3.0 ou ultérieure
(AGPL-3.0-or-later)**. En bref : si vous exécutez une version modifiée de Favilla
comme service réseau, vous devez en fournir le code source modifié à ses
utilisateurs. Texte complet dans [`LICENSE`](LICENSE).

<div align="center">
    <img width="300" height="100" alt="mobile-title" src="https://github.com/user-attachments/assets/ceeff067-98e1-4f7c-bb19-9585e501c275" />

<sub>Made in Italy 🇮🇹</sub>
</div>
