# Frontend architecture

**Status:** Agreed · **Owner:** product + engineering · **Last updated:** 2026-07-27

This document is the decision record for how WP BizWit builds interactive UI.
Implementation work is tracked in [`../plans/07-frontend-modernization.md`](../plans/07-frontend-modernization.md).

**Monorepo:** assets are built with npm workspaces + Turborepo from the
`wp-content/` git root — `npm run -w wp-bizwit build` (not a nested plugin-only
`npm install`). See [development.md](development.md) and
[monorepo AGENTS](../../../AGENTS.md).

---

## Goals (non-negotiable)

1. **Large custom product UI** inside wp-admin (and later optional front-facing
   surfaces), not only classic form posts.
2. **Reusable, extendable components** with rich props, owned by this plugin —
   not a third-party admin template we cannot control.
3. **Top-tier UX/UI** for Indonesian companies and UMKM: clear hierarchy, polite
   copy, WhatsApp-friendly flows, large money fields, progressive disclosure.
4. **Performance first for slow / remote connections** — chatty UIs and fat
   bundles are product bugs.
5. **Small shipped package** — users download only what runs; toolchains stay
   out of the zip.
6. **wordpress.org–ready** — local assets, GPL-compatible deps, human-readable
   source or public build tooling, no remote executable code.

These sit alongside the existing domain rules in [index.md](index.md): Indonesia
first, record-keeping only (no payment processing), region ≠ locale.

---

## Decision: stack

| Layer | Choice | Ships to user? |
|-------|--------|----------------|
| UI framework | **Vue 3** + TypeScript | Compiled JS only |
| Styling | **Tailwind CSS v4**, scoped under `.wp-bizwit` | Compiled CSS only |
| Bundler | **Vite 8** (Rolldown as engine) | No |
| Toolchain (lint/transform/minify) | **OXC** family via Vite/Rolldown; optional Oxlint | No |
| Data from PHP | **Custom REST** under `wp-bizwit/v1` (+ nonces / cookies for cookie auth) | N/A |
| Domain / security / documents | **Existing PHP** (`Repositories`, `Money`, `Region`, screens) | PHP as today |
| Gutenberg blocks (if ever needed) | Optional; not in the current Vite pipeline — add a dedicated path only if blocks return | Block assets if any |

### Explicitly rejected for the distributable plugin

| Rejected | Why |
|----------|-----|
| **Laravel Livewire** | Server round-trip per interaction hurts slow networks; fights Vue as the interaction model; adds package weight |
| **Roots Acorn** | Laravel runtime inside a plugin is heavy, host-fragile, and wrong abstraction when repositories already exist. Fine later for a hosted Bedrock product, not for wordpress.org BizWit |
| **Vue + Livewire together** | Two UI models, double cost, inconsistent UX |
| **CDN-loaded app JS/CSS** | wordpress.org guideline: non-service assets must be local |
| **Full admin SPA replacing all of wp-admin chrome** | Hijacks admin; worse progressive load; harder a11y with core |
| **Heavy UI kits as default** (Vuetify, Element Plus, full Ant Design) | Bundle bloat; prefer headless primitives + our design system |

---

## Architecture shape

```
wp-admin (WordPress chrome)
 └── BizWit menu pages (PHP still owns capability checks + page shell)
      ├── Simple surfaces → PHP views (settings, placeholders, empty states)
      └── Heavy surfaces  → Vue app shell / islands
           ├── mount #wp-bizwit-app (or per-island roots)
           ├── Vue Router (hash or path under admin page query)
           ├── Pinia only if cross-screen state is proven necessary
           └── components from resources/ui (design system)

PHP domain (unchanged ownership)
 ├── REST controllers → repositories → $wpdb
 ├── Money / Region / Sequence / Capabilities
 └── Printable documents (server-rendered HTML/PDF later)
```

**PHP remains the source of truth** for tax gates, money arithmetic, document
numbers, permissions and sanitisation. Vue never reimplements PKP rules or
rupiah rounding.

**Progressive adoption:** new heavy screens (projects, invoices, payments) are
the first Vue homes. Existing Clients CRUD may stay PHP until a deliberate
rewrite; do not big-bang rewrite working screens.

---

## Interaction model

One model for product UI:

- **Vue owns client interactivity** (forms with rich controls, line items,
  filters, optimistic feedback, local draft state).
- **PHP owns state changes** via REST (or form POST for simple classic screens)
  with `permission_callback`, capabilities and nonces.
- **No Livewire `wire:model` path** in this plugin.

### Screen loading strategy

| Screen type | Approach |
|-------------|----------|
| Settings, simple lists | PHP `Screen` + views (current pattern) |
| Complex editors (invoice lines, termin builder) | Vue island or full page shell inside the admin page |
| Dashboard widgets later | Small Vue islands or server HTML + sparklines |

Prefer **multi-entry / route-level code splitting** over one mega-bundle for all
BizWit pages. Enqueue only the entry the current screen needs.

---

## Scoping and coexistence with wp-admin

1. Root wrapper: always render app inside  
   `<div class="wrap wp-bizwit" id="wp-bizwit-app" …>`.
2. Tailwind preflight must not restyle the whole admin. Use a **scoped** setup
   (important: limit preflight/base to `.wp-bizwit` via Tailwind v4
   `@layer` / important selector strategy documented in the plan).
3. Do not dequeue core admin styles globally.
4. Do not load Vue on non-BizWit admin pages.
5. Use WordPress’s registered jQuery **only if a screen still needs it**; new Vue
   code must not depend on jQuery.

---

## Performance budget (remote / slow links)

Targets are **gzipped**, production build, measured with a bundle visualizer and
Chrome throttling (Slow 3G or “Fast 3G” at minimum).

| Asset | Budget |
|-------|--------|
| Shared Vue runtime + app core (first heavy screen) | ≤ 60 KB |
| Per-screen async chunk | ≤ 50 KB |
| Admin CSS (all Tailwind for BizWit) | ≤ 25 KB |
| Additional chart / PDF / editor chunk | Lazy only; ≤ 80 KB when opened |

### Required tactics

- Code-split by screen/route; no invoice editor code on the clients list.
- Lazy-load heavy optional libs (charts, rich text, PDF) on open.
- Skeleton UI while REST loads; never blank white for 3 seconds.
- Prefer server-paginated lists; virtualise only when lists are large.
- Cache REST list responses in memory for the session where safe.
- No automatic polling; prefer explicit refresh or rare intervals.
- Ship `build/*.js` and `build/*.css` only — no `node_modules` in the zip.

Review these budgets before tagging any release that ships a new heavy screen.

---

## Design system principles

Location: `resources/ui/` (Vue SFCs) + Tailwind tokens in `resources/styles/`.

| Principle | Practice |
|-----------|----------|
| Domain props | `amountMinor: number`, `currency: string` — format via shared helpers that mirror `Support\Money` rules |
| Indonesia-aware | Money display `Rp 1.500.000`; dates `26 Juli 2026`; full-name fields; never require surname |
| Progressive disclosure | Complex fields behind sections; summaries show current state |
| Accessibility | Label association, keyboard, focus rings, contrast; native controls first |
| Copy | `Anda`, `Silakan` / `Mohon` in id_ID; domain terms stay Indonesian (faktur, kwitansi, NPWP) |
| Extensibility | Prefer composition (slots) and typed props over deep option bags |

Avoid inventing a parallel formatting layer that drifts from PHP. When in doubt,
format on the server for documents; on the client only for interactive preview
using the same algorithms documented in [indonesia.md](indonesia.md).

---

## REST API conventions

Namespace: `wp-bizwit/v1`

| Rule | Detail |
|------|--------|
| Auth | Cookie auth + `X-WP-Nonce` (`wp_rest`) for admin SPA |
| Caps | Every route `permission_callback` uses BizWit capabilities |
| Money | Integer minor units in JSON; never floats |
| Errors | `WP_Error` with machine `code` + human message (translated) |
| Lists | Pagination: `page`, `per_page`, total headers |
| Writes | Validate/sanitise in repository layer (same as form POSTs) |

REST is for the Vue shell. Classic PHP screens may keep PRG form posts. Do not
build two divergent write paths with different validation — both call the same
repository methods.

---

## wordpress.org packaging

| Requirement | How we meet it |
|-------------|----------------|
| Local JS/CSS | Built assets under `build/`; no CDN for app code |
| No re-bundled WP defaults | Do not ship our own jQuery/Moment/etc. if WP provides them |
| Source available | Public Git repo + `package.json` scripts documented in [development.md](development.md) |
| GPL-compatible | Audit every npm/Composer runtime dep before adding |
| Readable code | PHP human-readable; JS is built — source in repo |
| Complete plugin zip | `npm run build` in release CI; include `build/` |

Plugin Check (PCP) should run in CI before release candidates.

### wordpress.org packaging checklist

Use this before a release candidate or `.org` zip:

1. **Local assets only** — `Admin\Assets` and `WP_BizWit` enqueue from
   `build/` via `WP_BIZWIT_URL` / plugin path. No CDN, unpkg, or jsDelivr for
   app JS/CSS. Optional Vite HMR (`WP_BIZWIT_VITE_DEV`) is local-dev only and
   must stay off in production and release builds.
2. **`npm run build` before zip** — release CI always rebuilds frontend assets
   (setup `mode: prod`); never ship a zip that relies on a stale cached `build/`.
   Local: run `npm run build` before `wp dist-archive` or manual packaging.
3. **Include `build/`** — hashed JS/CSS + `manifest.json` must be inside the
   plugin directory in the zip (`build/` is gitignored; CI/artifact supplies it).
4. **Runtime npm is Vue only** — `package.json` `dependencies` should stay
   `{ "vue": "…" }` unless a new runtime dep is budget-justified and
   GPL-compatible. Vue is MIT (GPL-compatible). DevDependencies (Vite, Tailwind,
   TypeScript, visualizer, …) must not appear in the zip.
5. **Source on GitHub** — human-readable PHP + `resources/` TS/Vue; build tooling
   is public (`package.json`, `vite.config.ts`).
6. **Plugin Check** — run the WordPress.org Plugin Check Plugin against the
   release zip before RC; fix reported blockers.
7. **No payment processing** — confirm scope still matches README (record-keeping
   only); unrelated to assets but required for product honesty on `.org`.

Measured sizes and re-measure commands: [performance.md](performance.md).

---

## Dependency policy (frontend)

Same spirit as [development.md](development.md#dependency-policy):

- Prefer zero runtime npm dependencies beyond Vue (and intentional tiny helpers).
- **Current runtime `dependencies`:** `vue` only (MIT, GPL-compatible).
- Any new runtime package must be: widely used / well maintained, updated within
  ~8 months, trusted maintainer — **and** justified against the performance budget.
- Prefer first-party components over another UI framework.
- DevDependencies (Vite, Tailwind, TypeScript, `rollup-plugin-visualizer`, …)
  are free of ship-size cost if they never land in the plugin zip.

---

## Relation to feature plans

| Plan | Frontend expectation |
|------|----------------------|
| [01 Projects](../plans/01-projects.md) | First candidate for Vue form (termin builder) once foundation lands |
| [02 Invoices](../plans/02-invoices.md) | Primary Vue shell (line items, tax preview) |
| [03 Payments](../plans/03-payments-receipts.md) | Vue for allocation UI; print stays server |
| [04 UX](../plans/04-ux-and-onboarding.md) | Shared empty states, skeletons from design system |
| [07 Frontend modernization](../plans/07-frontend-modernization.md) | Tooling, bootstrap, pilot, budgets — **this foundation** |

Feature plans must not reintroduce Livewire, Acorn, or a second SPA framework.

---

## Open decisions (allowed to revisit with evidence)

1. **Vue Router mode** in wp-admin (`?page=` + hash vs query segments).
2. **Pinia** — only if multiple screens share complex client state.
3. **Whether Clients list migrates to Vue** before 1.0 — default no.
4. **Headless primitive library** (e.g. Reka UI) vs pure first-party controls —
   decide at design-system start with a size spike.
5. **Hosted SaaS product** later may use Acorn/Bedrock; that is a different
   deliverable from the wordpress.org plugin.
