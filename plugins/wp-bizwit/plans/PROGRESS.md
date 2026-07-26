# Progress Tracker

What is actually true of the codebase right now. Update this in the same commit
as the work it describes — a tracker that lags is worse than none.

**Current version: 0.3.0 — beta.** Pre-1.0 on purpose: three of the six admin
screens are placeholders, and the data model may still change in ways that need
a migration. Do not run a real business on this yet.

---

## Feature status

| Area | Status | Notes |
|------|--------|-------|
| Database schema (7 tables) | ✅ Done | All tables exist, including for unbuilt screens |
| Versioned migrations | ✅ Done | `Installer`, checked on `plugins_loaded` |
| Capabilities and roles | ✅ Done | 6 caps, BizWit Manager + BizWit Staff |
| Regional profile layer | ✅ Done | `Localization\` — Indonesia + generic |
| Indonesian translation | ✅ Done | 240/240 strings |
| Money handling | ✅ Done | Integer minor units, zero-decimal IDR, terbilang |
| Atomic document numbering | ✅ Done | `Sequence`, Indonesian and simple formats |
| Settings screen | ✅ Done | Progressive disclosure, tax fully optional |
| Dashboard | 🟡 Basic | Tiles only; no charts, no ageing |
| **Clients** | ✅ Done | List, search, filter, sort, bulk actions, add/edit, delete guard |
| Client contacts | ⬜ Not started | Table exists, no UI |
| **Projects** | ⬜ Placeholder | [Plan](01-projects.md) |
| **Invoices** | ⬜ Placeholder | [Plan](02-invoices.md) |
| **Payments / kwitansi** | ⬜ Placeholder | [Plan](03-payments-receipts.md) |
| Printable documents | ⬜ Not started | Part of the invoice and payment plans |
| Onboarding | ⬜ Not started | [Plan](04-ux-and-onboarding.md) |
| Import / export | 🔒 Deferred | [Plan](05-import-export.md) — gated on 1.0 GA |
| Reports | ⬜ Not started | Not yet planned |
| REST API | ⬜ Not started | Not yet planned |

Legend: ✅ done · 🟡 partial · ⬜ not started · 🔒 deliberately deferred

## Quality status

| Check | State |
|-------|-------|
| PHPCS (WordPress standard) | ✅ 0 errors in plugin code |
| PHPStan level 6 | ✅ 0 errors |
| PHPUnit | 🟡 `MoneyTest`, `IndonesiaRegionTest` — repositories and screens untested |
| Accessibility | 🟡 Native `<details>`, form labels, list-table semantics. No audit yet |
| Security review | 🟡 Controls documented in [`../SECURITY.md`](../SECURITY.md); no external review |
| Browser testing | ⬜ Not done |

## Known gaps worth naming

1. **No test coverage on the repository layer.** `Client_Repository` sanitisation
   and the delete guard are load-bearing and untested. Highest-value test debt.
2. **No pagination stress test.** The clients list has never been run against a
   large dataset.
3. **`client_contacts` table has no UI.** It is written to only by the delete
   cascade.
4. **Dashboard figures are unbounded queries.** Fine at current scale; needs
   caching before it meets a large database.
5. **No audit trail.** Who changed an invoice, and when, is not recorded. Matters
   before anyone relies on this for tax records.
6. **Sequence numbers are consumed on allocation.** A failed save leaves a gap.
   Acceptable now, but see [02-invoices.md](02-invoices.md) for why it may not
   stay acceptable for faktur.

## Route to 1.0

1. `0.4.0` — Projects
2. `0.5.0` — Invoices, with printable output
3. `0.6.0` — Payments and kwitansi with terbilang and meterai
4. `0.7.0` — Onboarding, dashboard, UX polish
5. `0.8.0` — Repository and screen test coverage, accessibility pass
6. `0.9.0` — Security review, performance pass, release candidate
7. `1.0.0` — General availability
8. post-1.0 — Import / export

The import/export gate is deliberate: an importer that writes malformed rows into
a beta schema creates data problems that outlive the beta.
