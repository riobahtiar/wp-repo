# Progress Tracker

What is actually true of the codebase right now. Update this in the same commit
as the work it describes — a tracker that lags is worse than none.

**Current version: 1.2.0 shipped; line-item kinds/periods + document style sync
are implemented (Unreleased → next minor).** Schema is frozen for
additive-only changes. External security review remains recommended for
high-risk installs ([docs/security-self-review.md](../docs/security-self-review.md)).

**Git / tooling:** package lives in monorepo `wp-content/` (`wp-repo`). JS:
`npm install` + `npm run -w wp-bizwit …` from monorepo root. See package
[`AGENTS.md`](../AGENTS.md) and monorepo [`AGENTS.md`](../../../AGENTS.md).

---

## Feature status

| Area | Status | Notes |
|------|--------|-------|
| Database schema (9 tables) | ✅ Done | Additive through `1.7.0` · freeze at 1.0 |
| Versioned migrations | ✅ Done | `Installer` `1.7.0` (line item meta columns) |
| Capabilities and roles | ✅ Done | + template CPT caps |
| Regional profile layer | ✅ Done | Indonesia + generic |
| Indonesian translation | ✅ Done | Catalogue kept in step with features |
| Money handling | ✅ Done | Integer minor units, terbilang |
| Atomic document numbering | ✅ Done | Invoice + receipt sequences |
| Settings screen | ✅ Done | Progressive disclosure, tax optional |
| **Dashboard** | ✅ Done | Checklist, tiles, ageing, activity, recent |
| **Clients** | ✅ Done | Full CRUD + empty state |
| Client contacts | ⬜ Not started | Table exists, no UI |
| **Projects** | ✅ Done | Termin, retensi · [01](01-projects.md) |
| **Invoices** | ✅ Done | List/form/print · kind + billing period · [02](02-invoices.md) |
| Line item kind / period | ✅ Done | Schema 1.7.0 · `Line_Item_Meta` · form + print subline |
| **Payments / kwitansi** | ✅ Done | Record, settle, print · [03](03-payments-receipts.md) |
| Printable documents | ✅ Done | Shared type scale / tokens · gallery pack v7 |
| **Document templates** | ✅ Done | Vue A4 layout builder · ramp-aligned defaults |
| **Onboarding** | ✅ Done | Dismissible checklist · [04](04-ux-and-onboarding.md) |
| **Audit trail** | ✅ Done | Activity log · [06](06-hardening-and-release.md) |
| Import / export | 🔒 Deferred | [05](05-import-export.md) — unblocked post-1.0 |
| Reports | 🟡 Partial | Ageing on dashboard only |
| REST API | 🟡 Health only | |
| Frontend (Vue/Vite/Tailwind) | ✅ Foundation | Template builder + dashboard pilot |

Legend: ✅ done · 🟡 partial · ⬜ not started · 🔒 deliberately deferred

## Quality status

| Check | State |
|-------|-------|
| PHPCS | ✅ 0 errors in plugin code |
| PHPStan level 6 | ✅ 0 errors |
| PHPUnit | ✅ Repos, sequence, upgrade, line-item meta, layout enrichment (+ known env flakes on full suite) |
| Vitest | 🟡 `formatMoney` only |
| Bundle budgets | ✅ Soft gate |
| Accessibility | 🟡 Skip link, landmarks, captions, focus-visible; formal AA optional later |
| Security review | ✅ Self-review closed · external optional |
| Browser testing | ⬜ Manual smoke on local |
| Schema freeze | ✅ Additive-only from 1.0 · [upgrade.md](../docs/upgrade.md) |

## Route past 1.0

1. ~~0.3–0.9 — Foundation through RC~~ ✅
2. ~~1.0.0 — GA~~ ✅
3. post-1.0 — Import / export · [05](05-import-export.md)
4. post-1.0 — External security review (enterprise)
5. post-1.0 — Client contacts UI, richer reports
