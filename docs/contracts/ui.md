# UI — views, design system, theming

> On-demand contract. Entry point and map: [`CLAUDE.md`](../../CLAUDE.md).
> Tags: `MUST` non-negotiable · `SHOULD` default unless justified · `NOTE` practical reference.

## 1. View basics
- `MUST`: first line of a full view is `$view->layout('main');` (or `auth` for login/auth pages).
- `MUST`: wrap main content with `$view->start('content')` … `$view->end()`.
- `MUST`: `<?= e($var) ?>` for all untrusted output; `<?= csrf_field() ?>` in every mutating form; `has_permission('slug')` for permission checks; inline scripts use `nonce="<?= e(csp_nonce()) ?>"`.
- `MUST`: layout names match `/^[a-zA-Z0-9_\-]+$/`.

## 2. Includes & assets
- `MUST`: include fragments with `$view->include('Module/Views/partials/name', $vars)`.
- `SHOULD`: register module assets in full views via `$view->pushStyle('css/module.css')` / `$view->pushScript('js/module.js')`.
- `MUST`: do not use `pushStyle()`/`pushScript()` inside shared layout partials — use direct tags there.

## 3. HTMX lists & filters
| Attribute | Contract |
|---|---|
| `hx-get` | index route or partial endpoint |
| `hx-trigger` | `keyup changed delay:400ms` for inputs, `change` for selects |
| `hx-target` | the partial container to replace |
| `hx-push-url` | `true` to keep URL / back button / state |
| `hx-include` | MUST include the other active filters |
- `MUST`: every filter includes the others via `hx-include`, or the UI state is lost.
- Table partial: `SHOULD` use `sort_context()` / `sort_link()` for sort links; `MUST NOT` pass their output through `e()`. Pagination uses `hx-get`/`hx-target`/`hx-push-url` consistent with the filters.
- Live search: `SHOULD` return a dedicated `Views/partials/search-results.php`.

## 4. Form contract
| Aspect | Contract |
|---|---|
| create/edit | same `form.php`, `$item === null` for create |
| HTML update | `_method=PUT` hidden field |
| errors | `.is-invalid` + `<div class="invalid-feedback">` |
| old values | `$old['field'] ?? $item['field'] ?? ''` |
| footer | aligned `Annulla` / `Salva` actions |
- `MUST`: invalid fields read errors from the standard `['field' => ['msg']]` shape.
- `SHOULD` (advanced forms): `fieldset.app-form-section` with collapsible header; `novalidate data-app-form` to hook the shared validation runtime (auto-expands invalid sections, focuses the first error); `data-char-counter` / `data-tag-preview`; ARIA (`aria-expanded/controls/required/invalid/describedby`).
- `MUST` (HTMX inline errors): return the same partial with status `422` and `invalid-feedback` markup; reserve `422 application/json` for non-inline / global errors (the shared runtime auto-swaps only `422 text/html`).

## 5. Detail page
- `SHOULD`: two-column detail with an info card, an actions card, and a danger-zone card when delete exists.

## 6. Design system (mandatory)
- `MUST`: every page works in light AND dark mode; use CSS tokens (`var(--bg-surface)`, `var(--text-primary)`, `var(--border-color)`); use `var(--accent-color)` for indicators/borders/badges, not as a dominant background.
- `MUST`: every icon-only button / interactive element has a Bootstrap tooltip; labels/titles/placeholders in Italian (except non-translatable technical names); no hardcoded colors or static inline styles; Bootstrap 5 first, custom module classes only after; never reinvent page hero headers.
- `SHOULD`: qualitative reference is the profile page (`/localhost/public/profile`).

Shared tokens:
| Family | Tokens |
|---|---|
| spacing | `--card-padding`, `--card-gap`, `--section-gap`, `--form-field-gap` |
| radius | `--radius-sm/md/lg` |
| icons | `--icon-xs/sm/md/lg/xl` (+ `.icon-*` utilities) |
| colors | `--bg-primary`, `--bg-surface`, `--text-primary/secondary/muted`, `--border-color(-strong)`, `--accent-color`, `--shadow-sm/md` |

Hero per page type (`MUST` use the shared partials, not custom heroes):
| Page type | Partial |
|---|---|
| user-facing index | `pf-hero-user` |
| user-facing show/edit/create | `pf-hero-module` |
| any admin page | `pf-hero-admin` |
- `SHOULD`: build hero buttons as a raw HTML string in PHP and pass it to the partial.

Components: buttons → Bootstrap `.btn-*`, JS hooks via `data-*`; icons → `.icon-*` utilities (no `style="font-size"`); cards → `.card`/`.card-header`/`.card-body` + `.app-card-icon`; input with icon → `.input-group` + `.input-group-text.app-input-icon`; upload → `.app-dropzone`. Legacy aliases: `.pf-card-header-icon`, `.pf-input-icon`, `.pf-drop-zone`.

Module CSS/JS: `MUST` live in `public/assets/{css,js}/`, prefixed classes, JS wrapped in an IIFE, no inline styles. `SHOULD` reinitialize Bootstrap tooltips after `htmx:afterSwap`.

## 7. Charting
| Library | Path | When |
|---|---|---|
| ApexCharts | `public/assets/js/apexcharts.min.js` | dashboard widgets, KPI cards, quick charts |
| Plotly.js v3.5.1 | `public/assets/js/vendor/plotly.min.js` | advanced analytics (scatter, heatmap, box, complex time series, SVG/PNG export) |
- `SHOULD`: dashboard `chart` widgets use ApexCharts (matches the `chartId`/`chartType`/`series`/`options` contract); analytics modules use Plotly.
- `MUST`: charts/widgets reread CSS tokens via `window.FavillaTheme.readCssVar()` and rebuild after `favilla:theme-state-changed`.

## 8. Theme runtime
- `MUST`: the theme runtime is centralized in `window.FavillaTheme` (`public/assets/js/app.js`); modules/views must not create a second theme manager.
- API: `getState()` (returns `theme`, `skin`, `font`, `sidebarStyle`, `accent`, `pattern`), `readCssVar('--token', fallback)`, and `apply*`/`persist*` for theme/accent/skin/font/sidebarStyle/pattern.
- `MUST`: react to `favilla:theme-state-changed` for reactive components; preserve on `<html>` the attributes `data-bs-theme`, `data-theme-skin`, `data-theme-font`, `data-theme-pattern`, `data-sidebar-style`; header and profile delegate to `window.FavillaTheme`.
