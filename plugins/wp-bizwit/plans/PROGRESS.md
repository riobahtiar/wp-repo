# Progress Tracker

What is actually true of the codebase right now. Update this in the same commit
as the work it describes — a tracker that lags is worse than none.

**Current version: 0.8.0 — beta.** Pre-1.0 on purpose: full a11y audit and
external security review are still open, and the schema may still change.

**Git / tooling:** package lives in monorepo `wp-content/` (`wp-repo`). JS:
`npm install` + `npm run -w wp-bizwit …` from monorepo root. See package
[`AGENTS.md`](../AGENTS.md) and monorepo [`AGENTS.md`](../../../AGENTS.md).

---

## Feature status

| Area | Status | Notes |
|------|--------|-------|
| Database schema (8 tables) | ✅ Done | Payments withholding cols (1.4.0) |
| Versioned migrations | ✅ Done | `Installer` `1.4.0` |
| Capabilities and roles | ✅ Done | + template CPT caps |
| Regional profile layer | ✅ Done | Indonesia + generic |
| Indonesian translation | ✅ Done | Catalogue kept in step with features |
| Money handling | ✅ Done | Integer minor units, terbilang |
| Atomic document numbering | ✅ Done | Invoice + receipt sequences |
| Settings screen | ✅ Done | Progressive disclosure, tax optional |
| **Dashboard** | ✅ Done | Checklist, tiles, ageing, recent, quick actions |
| **Clients** | ✅ Done | Full CRUD + empty state |
| Client contacts | ⬜ Not started | Table exists, no UI |
| **Projects** | ✅ Done | Termin, retensi · [01](01-projects.md) |
| **Invoices** | ✅ Done | List/form/print · [02](02-invoices.md) |
| **Payments / kwitansi** | ✅ Done | Record, settle, print · [03](03-payments-receipts.md) |
| Printable documents | ✅ Done | Layout builder + kwitansi HTML |
| **Document templates** | ✅ Done | Vue A4 layout builder |
| **Onboarding** | ✅ Done | Dismissible checklist · [04](04-ux-and-onboarding.md) |
| Import / export | 🔒 Deferred | [05](05-import-export.md) |
| Reports | 🟡 Partial | Ageing on dashboard only |
| REST API | 🟡 Health only | |
| Frontend (Vue/Vite/Tailwind) | ✅ Foundation | Template builder + dashboard pilot |

Legend: ✅ done · 🟡 partial · ⬜ not started · 🔒 deliberately deferred

## Quality status

| Check | State |
|-------|-------|
| PHPCS | ✅ 0 errors in plugin code |
| PHPStan level 6 | ✅ 0 errors |
| PHPUnit | 🟡 Expanded: clients, layout, onboarding, stats (wp-env) |
| Vitest | 🟡 `formatMoney` only |
| Bundle budgets | ✅ Soft gate |
| Accessibility | 🟡 Skip link, notice focus, dirty forms, focus-visible; formal AA later |
| Security review | 🟡 Documented in SECURITY.md |
| Browser testing | ⬜ Manual smoke on local |

## Route to 1.0

1. ~~0.3–0.6 — Foundation through payments + templates~~ ✅
2. ~~0.7.0 — Onboarding, dashboard, empty states~~ ✅
3. ~~0.8.0 — Test coverage, a11y hardening~~ ✅
4. `0.9.0` — Security + performance RC
5. `1.0.0` — GA
6. post-1.0 — Import / export
