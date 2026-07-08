# Platform contracts

Topic-by-topic contracts for building on Favilla. These are the **on-demand**
companions to [`CLAUDE.md`](../../CLAUDE.md) (the always-load entry point): open
the one that matches what you're working on. Each uses the tags **`MUST`**
(non-negotiable) · **`SHOULD`** (default unless justified) · **`NOTE`**
(practical reference); on the same topic the most restrictive rule wins.

| Contract | Read it when you're… |
|---|---|
| [architecture.md](architecture.md) | …learning the request lifecycle, layering, module anatomy, routing or auto-discovery |
| [security.md](security.md) | …handling input, output, SQL, sessions, CSRF, uploads or SSO |
| [data.md](data.md) | …writing schema, migrations, SQL conventions or a Repository |
| [ui.md](ui.md) | …building views, HTMX lists/forms, the design system or theming |
| [i18n.md](i18n.md) | …adding user-facing copy, translations or a new locale |
| [integrations.md](integrations.md) | …reusing notifications, dashboard, search, export, contacts, calendar or the scheduler |
| [editions.md](editions.md) | …touching the Developer / Personal / Team profiles or release packaging |
| [building-a-module.md](building-a-module.md) | …starting a new module — step-by-step workflow + one minimal example per layer |
| [gotchas.md](gotchas.md) | …stuck on a documented blocking error, or writing tests (SQLite vs MariaDB quirks) |

**New module?** Start from [building-a-module.md](building-a-module.md) and copy
patterns from real modules (`Contacts`, `Tasks`) or the scaffold stubs in
`app/Modules/_Template/stubs/` (`php favilla make:module`).

The machine-readable counterpart to these prose contracts is
[`project_context.json`](../../project_context.json) plus `context/<Module>.json`
(regenerate with `php favilla context:generate`).
