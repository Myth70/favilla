# Bibbia narrativa — Demo "Aurora Studio"

> Sorgente di verità unica per TUTTE le sezioni seed. Ogni riferimento incrociato
> (contatto ↔ cliente ↔ progetto ↔ documento ↔ articolo ↔ chat) deve tornare qui.
> Date: anno corrente dinamico (`CONCAT(YEAR(CURDATE()),'-MM-DD ...')`) + offset relativi
> solo per l'attività recente. ID fissi da 9001, INSERT IGNORE.

## Lo studio
**Aurora Studio** — studio digitale con **team remoto internazionale**. Lingua di lavoro
comune: **inglese** (gruppi/annunci/blog). Tra colleghi della stessa nazione si parla in
lingua madre (dirette Teams).

## Cast (users) — id, ruolo applicativo, mansione, lingua UI
| id | Nome | Naz | Ruolo app | Mansione | lang |
|----|------|-----|-----------|----------|------|
| 1  | Amministratore | — | Admin | Founder/owner dello studio | it |
| 3  | Giulia Ferrari | IT | Manager | Project lead (delivery) | it |
| 4  | James Whitaker | EN | Manager | Account/commercial lead | en |
| 5  | Anna Weber | DE | Manager | Operations & quality lead | de |
| 6  | Marco Bianchi | IT | User | Backend developer | it |
| 7  | Emily Turner | EN | User | Content & copywriter | en |
| 8  | Julien Moreau | FR | User | Frontend developer | fr |
| 9  | Camille Laurent | FR | User | UX/UI designer | fr |
| 10 | Lukas Braun | DE | User | DevOps / infrastruttura | de |
| 11 | Diego Herrera | ES | User | Fullstack developer | es |
| 12 | Lucía Romero | ES | User | Social & marketing | es |

Coppie stessa-nazione (dirette in lingua madre): IT 3↔6 · EN 4↔7 · FR 8↔9 · DE 5↔10 · ES 11↔12.

## Clienti (contacts, rubrica admin) — distinti dai nomi del team
| Cliente | Sede | Contatto referente | Progetto collegato |
|---------|------|--------------------|--------------------|
| Rossetti Calzature S.r.l. | Milano (IT) | Giulia Rossetti (Titolare) | E-commerce Rossetti |
| Bottega Verde Bio | Torino (IT) | Paolo Sartori (Marketing) | Campagna Bottega Verde |
| Nordlys Interiors GmbH | Berlino (DE) | Klara Hoffmann (Head of Brand) | Nordlys Rebrand & Site |
| (fornitori/partner) | vari | ServerPlanet, PrintLab, Fontana&Partners… | — |

## Progetti (4) — timeline scaglionata sull'anno dinamico
| id | Nome | code | Cliente | Owner | Stato | Timeline (anno corrente) |
|----|------|------|---------|-------|-------|--------------------------|
| 9001 | Sito e-commerce Rossetti | ECOM-ROS | Rossetti | Giulia Ferrari (3) | active | avvio -3 mesi → go-live +2 mesi |
| 9002 | Campagna lancio Bottega Verde | CAMP-BV | Bottega Verde | James Whitaker (4) | completed | Q1 (chiusa) |
| 9003 | Gestionale interno Aurora | GEST-INT | (interno) | Anna Weber (5) | active | avvio recente → +3 mesi (fase iniziale) |
| 9004 | Nordlys Rebrand & Site | NORD-BR | Nordlys | James Whitaker (4) | planning | kickoff Q4 (upcoming) |

Membri principali:
- ECOM-ROS: Giulia(3) owner, Marco(6) backend, Julien(8) frontend, Camille(9) design, admin(1) viewer.
- CAMP-BV: James(4) owner, Emily(7) copy, Lucía(12) social, Diego(11) dev.
- GEST-INT: Anna(5) owner, Marco(6), Lukas(10) infra, Diego(11).
- NORD-BR: James(4) owner, Camille(9) design, Julien(8), Emily(7).

## Timeline eventi chiave (anno dinamico)
- **Q1** — Campagna Bottega Verde chiusa (+38% iscritti newsletter).
- **~-3 mesi** — Kickoff e-commerce Rossetti.
- **~-1 mese** — Kickoff gestionale interno.
- **mid-year** — Team offsite / ritrovo annuale (blog recap con foto).
- **~+2 mesi** — Go-live e-commerce Rossetti (milestone).
- **Q4** — Kickoff Nordlys Rebrand.

## Mappa cross-modulo (coerenza)
- **Rossetti**: contact Giulia Rossetti → progetto ECOM-ROS → documento "Offerta e-commerce" +
  "Contratto Rossetti" → articoli/chat che citano l'e-commerce → task "offerta e-commerce".
- **Bottega Verde**: contact Paolo Sartori → progetto CAMP-BV (completato) → documento
  "Contratto quadro Bottega Verde" → **blog** "Bottega Verde campaign: +38%" → chat gruppo che
  festeggia il risultato (reaction 🎉) → menzione a Lucía/Emily.
- **Nordlys**: contact Klara Hoffmann → progetto NORD-BR (planning) → chat gruppo di kickoff →
  blog "New client: Nordlys".
- **Ferie/procedure**: documento "Procedura gestione ferie" (in approvazione) → blog "New leave
  policy" → chat gruppo con rimando al documento → evento calendario "Chiusura estiva".

## Lingue dei contenuti
- **Blog**: inglese (categorie/tag/commenti EN).
- **Gruppi Teams**: inglese. **Dirette stessa-nazione**: lingua madre (IT/FR/DE/ES) — showcase utf8mb4.
- **Task/Contatti/Documenti/Calendario**: italiano (studio con base IT + clienti IT/DE).
