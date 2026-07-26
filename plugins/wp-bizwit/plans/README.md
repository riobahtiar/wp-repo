# Plans

Working documents for everything not yet built, plus the reasoning behind how it
should be built. One file per body of work.

This package is part of the **wp-repo monorepo** (git root `wp-content/`). Agent
orientation: [`../AGENTS.md`](../AGENTS.md) · monorepo
[`../../../AGENTS.md`](../../../AGENTS.md). Implement features from a package
checkout that is still the monorepo tree — do not treat this folder as its own
git root for remotes/CI.

**These are plans, not promises.** Anything here can be re-scoped or dropped.
What is actually shipped lives in [`../CHANGELOG.md`](../CHANGELOG.md); what is
currently true of the codebase lives in [`PROGRESS.md`](PROGRESS.md).

## Index

| Plan | Status | Blocks release |
|------|--------|----------------|
| [01-projects.md](01-projects.md) | **Done** (0.4.0) | — |
| [02-invoices.md](02-invoices.md) | Planned | 0.5.0 |
| [03-payments-receipts.md](03-payments-receipts.md) | Planned | 0.6.0 |
| [04-ux-and-onboarding.md](04-ux-and-onboarding.md) | Partly done | 0.7.0 |
| [05-import-export.md](05-import-export.md) | **Deferred until 1.0 GA** | post-1.0 |
| [06-hardening-and-release.md](06-hardening-and-release.md) | Planned | 1.0.0 |
| [07-frontend-modernization.md](07-frontend-modernization.md) | **Done** (foundation; Plugin Check at RC) | Consumed by 0.4+ |

## Status vocabulary

| Status | Means |
|--------|-------|
| **Planned** | Agreed shape, not started |
| **In progress** | Being built now |
| **Partly done** | Some of it shipped; the rest is still listed here |
| **Deferred** | Deliberately not now, with a stated gate |
| **Done** | Shipped — moved to the changelog and removed from the index |
| **Dropped** | Decided against, with the reason kept for the record |

## What belongs in a plan file

Each plan states:

1. **Goal** — one paragraph, in terms of what the user can then do.
2. **Why it is not trivial** — the parts that will bite. This is the most
   valuable section; anyone can list CRUD screens.
3. **Scope** and explicit **out of scope**.
4. **Tasks** as checkboxes, ordered so each leaves the plugin working.
5. **Acceptance criteria** — observable, not "it works".
6. **Risks and open questions** — including anything needing a decision from the
   business owner rather than the developer.

Keep them honest. A plan that hides the hard part is worse than no plan.

## Ground rules that apply to every plan

These are settled and should not be relitigated per feature:

- **BizWit never processes, moves or holds money.** Recording only.
- **Indonesian companies and UMKM are the primary users**, and the plugin must
  stay usable by a freelancer with no tax obligation at all. Every new screen
  needs both to work. See [`../docs/indonesia.md`](../docs/indonesia.md) and
  [`../docs/culture.md`](../docs/culture.md).
- **Simple by default, complexity behind disclosure.** New fields that only some
  users need go inside a `<details>` section, not into the default view.
- **Ship the Indonesian translation with the feature.**
- **All `$wpdb` access through a repository**; nonce + capability on every state
  change. See [`../SECURITY.md`](../SECURITY.md).
