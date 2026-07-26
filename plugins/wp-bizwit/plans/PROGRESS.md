# Progress Tracker

What is actually true of the codebase right now. Update this in the same commit
as the work it describes — a tracker that lags is worse than none.

**Current version: 0.6.0 — beta.** Pre-1.0 on purpose: onboarding and reports
are thin, and the data model may still change. Do not run a real business on
this yet.

**Git / tooling:** package lives in monorepo `wp-content/` (`wp-repo`). JS:
`npm install` + `npm run -w wp-bizwit …` from monorepo root. See package
[`AGENTS.md`](../AGENTS.md) and monorepo [`AGENTS.md`](../../../AGENTS.md).

---

## Feature status

| Area | Status | Notes |
|------|--------|-------|
| Database schema (8 tables) | ✅ Done | Payments withholding cols (1.4.0) |
| Versioned migrations | ✅ Done | `Installer` `1.4.0` |
| Capabilities and roles | ✅ Done | 6 caps, BizWit Manager + BizWit Staff |
| Regional profile layer | ✅ Done | Indonesia + generic |
| Indonesian translation | ✅ Done | id_ID includes invoices + payments |
| Money handling | ✅ Done | Integer minor units, terbilang, line qty math |
| Atomic document numbering | ✅ Done | Invoice + receipt sequences |
| Settings screen | ✅ Done | Progressive disclosure, tax optional |
| Dashboard | 🟡 Basic + Vue pilot | No charts/ageing |
| **Clients** | ✅ Done | Full CRUD |
| Client contacts | ⬜ Not started | Table exists, no UI |
| **Projects** | ✅ Done | Termin, retensi · [01](01-projects.md) |
| **Invoices** | ✅ Done | List/form/print, PPN/PPh 23 · [02](02-invoices.md) |
| **Payments / kwitansi** | ✅ Done | Record, settle, print · [03](03-payments-receipts.md) |
| Printable documents | ✅ Done | Invoice + kwitansi HTML/print |
| Onboarding | ⬜ Not started | [04](04-ux-and-onboarding.md) |
| Import / export | 🔒 Deferred | [05](05-import-export.md) |
| Reports | ⬜ Not started | |
| REST API | 🟡 Health only | |
| Frontend (Vue/Vite/Tailwind) | ✅ Foundation | Invoice/payment editors still PHP |

Legend: ✅ done · 🟡 partial · ⬜ not started · 🔒 deliberately deferred

## Quality status

| Check | State |
|-------|-------|
| PHPCS | ✅ 0 errors in plugin code |
| PHPStan level 6 | ✅ 0 errors |
| PHPUnit | 🟡 Invoice + payment repository tests (wp-env) |
| Vitest | 🟡 `formatMoney` only |
| Bundle budgets | ✅ Soft gate |
| Accessibility | 🟡 No formal audit |
| Security review | 🟡 Documented in SECURITY.md |
| Browser testing | ⬜ Manual smoke on local |

## Known gaps

1. Multi-invoice allocation in one payment — deferred.
2. No audit trail of who changed records.
3. Dashboard unbounded queries at large scale.
4. Vue line-item / payment islands still optional.
5. Sequence gaps on failed writes after allocate — accepted for v1.

## Route to 1.0

1. ~~0.3.x — Frontend foundation~~ ✅
2. ~~0.4.0 — Projects~~ ✅
3. ~~0.5.0 — Invoices~~ ✅
4. ~~0.6.0 — Payments / kwitansi~~ ✅
5. `0.7.0` — Onboarding, dashboard, UX polish
6. `0.8.0` — Test coverage, accessibility pass
7. `0.9.0` — Security + performance RC
8. `1.0.0` — GA
9. post-1.0 — Import / export
