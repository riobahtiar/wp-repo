# Progress Tracker

What is actually true of the codebase right now. Update this in the same commit
as the work it describes — a tracker that lags is worse than none.

**Current version: 0.5.0 — beta.** Pre-1.0 on purpose: payments screens are
still placeholders, and the data model may still change in ways that need a
migration. Do not run a real business on this yet.

**Git / tooling:** package lives in monorepo `wp-content/` (`wp-repo`). JS:
`npm install` + `npm run -w wp-bizwit …` from monorepo root. See package
[`AGENTS.md`](../AGENTS.md) and monorepo [`AGENTS.md`](../../../AGENTS.md).

---

## Feature status

| Area | Status | Notes |
|------|--------|-------|
| Database schema (8 tables) | ✅ Done | `withholding_*` on invoices (1.3.0); invoice/payment tables ready |
| Versioned migrations | ✅ Done | `Installer` `1.3.0`, checked on `plugins_loaded` |
| Capabilities and roles | ✅ Done | 6 caps, BizWit Manager + BizWit Staff |
| Regional profile layer | ✅ Done | `Localization\` — Indonesia + generic |
| Indonesian translation | ✅ Done | id_ID catalogue includes invoices |
| Money handling | ✅ Done | Integer minor units, zero-decimal IDR, terbilang, line qty math |
| Atomic document numbering | ✅ Done | `Sequence`; draft provisional then permanent on send |
| Settings screen | ✅ Done | Progressive disclosure, tax fully optional |
| Dashboard | 🟡 Basic + Vue pilot | PHP tiles + Vue island; no charts/ageing |
| **Clients** | ✅ Done | List, search, filter, sort, bulk actions, add/edit, delete guard |
| Client contacts | ⬜ Not started | Table exists, no UI |
| **Projects** | ✅ Done | List/form, termin stages, retensi, delete guard · [Plan](01-projects.md) |
| **Invoices** | ✅ Done | List/form/print, status machine, PPN/PPh 23, overdue cron · [Plan](02-invoices.md) |
| **Payments / kwitansi** | ⬜ Placeholder | [Plan](03-payments-receipts.md) |
| Printable documents | 🟡 Invoices | Kwitansi still pending with payments |
| Onboarding | ⬜ Not started | [Plan](04-ux-and-onboarding.md) |
| Import / export | 🔒 Deferred | [Plan](05-import-export.md) — gated on 1.0 GA |
| Reports | ⬜ Not started | Not yet planned |
| REST API | 🟡 Health only | `GET /wp-bizwit/v1/health` + TS client; full CRUD later |
| Frontend (Vue/Vite/Tailwind) | ✅ Foundation done | Invoice v1 uses PHP line repeater; Vue editor still optional later |

Legend: ✅ done · 🟡 partial · ⬜ not started · 🔒 deliberately deferred

## Quality status

| Check | State |
|-------|-------|
| PHPCS (WordPress standard) | ✅ 0 errors in plugin code |
| PHPStan level 6 | ✅ 0 errors |
| PHPUnit | 🟡 + `InvoiceTotalsTest`, `InvoiceRepositoryTest` (run via `npm run test:php` / wp-env) |
| Vitest (JS) | 🟡 `formatMoney` only (`npm run test:unit`) |
| Bundle budgets | ✅ Soft gate (`npm run check:bundle-size`) |
| Accessibility | 🟡 Native `<details>`, form labels, list-table semantics. No audit yet |
| Security review | 🟡 Controls documented in [`../SECURITY.md`](../SECURITY.md); no external review |
| Browser testing | ⬜ Manual smoke on local install |

## Known gaps worth naming

1. **Thin test coverage on the repository layer.** Client repository still thin;
   invoice/project covered for core paths only.
2. **No pagination stress test.** Lists have not been run against large datasets.
3. **`client_contacts` table has no UI.**
4. **Dashboard figures are unbounded queries.** Fine at current scale.
5. **No audit trail.** Who changed an invoice, and when, is not recorded.
6. **Sequence gaps after allocation.** Permanent numbers allocate on leave-draft;
   a failed later write can still leave a gap — accepted for v1.
7. **Invoice line editor is PHP**, not the Vue shell plan 02 mentioned. Vue can
   replace the repeater later without schema changes.
8. **Payments do not yet drive partial/paid status** — mark manually until 0.6.0.

## Route to 1.0

1. ~~`0.3.x` — Frontend foundation~~ ✅ ([07](07-frontend-modernization.md))
2. ~~`0.4.0` — Projects~~ ✅ ([01](01-projects.md))
3. ~~`0.5.0` — Invoices~~ ✅ ([02](02-invoices.md))
4. `0.6.0` — Payments and kwitansi with terbilang and meterai
5. `0.7.0` — Onboarding, dashboard, UX polish
6. `0.8.0` — Repository and screen test coverage, accessibility pass
7. `0.9.0` — Security review, performance pass (bundle budgets), release candidate
8. `1.0.0` — General availability
9. post-1.0 — Import / export

The import/export gate is deliberate: an importer that writes malformed rows into
a beta schema creates data problems that outlive the beta.

**Stack boundary:** the distributable plugin does **not** use Livewire or Roots
Acorn. See [`../docs/frontend-architecture.md`](../docs/frontend-architecture.md).
