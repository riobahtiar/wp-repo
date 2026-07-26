# Performance baselines

**Last measured:** 2026-07-27 · **Stack:** Vite 8 + Vue 3 + Tailwind v4 (sole pipeline)

Budgets are **gzipped** production assets. Product rule: chatty UIs and fat
bundles are bugs for remote / slow connections. Source of truth for targets:
[frontend-architecture.md](frontend-architecture.md#performance-budget-remote--slow-links).

---

## Budgets

| Asset | Max gzipped |
|-------|-------------|
| Shared / admin core JS | 60 KB |
| Dashboard (or pilot) chunk JS | 50 KB |
| Admin CSS (all Tailwind for BizWit) | 25 KB |
| Optional heavy chunk (charts, PDF, …) | Lazy only; 80 KB when opened |

---

## Baseline (2026-07-27)

Measured after `npm run build` with `npm run check:bundle-size` (Node zlib gzip
level 9). Vite’s own “gzip” column may differ slightly by level.

| File (content-hashed) | Raw | Gzip | Role | Budget | Status |
|-----------------------|-----|------|------|--------|--------|
| `admin-*.js` | ~0 KiB | **0.02 KiB** | Shared admin entry (`resources/admin/main.ts`) | ≤ 60 KiB | OK |
| `dashboard-*.js` | ~64.2 KiB | **24.95 KiB** | Dashboard Vue pilot (includes Vue runtime) | ≤ 50 KiB | OK |
| `admin-*.css` | ~10.8 KiB | **2.58 KiB** | Scoped Tailwind + product CSS under `.wp-bizwit` | ≤ 25 KiB | OK |

**Headroom:** dashboard ~50% of budget; CSS well under; shared JS negligible
until a second Vue screen forces a shared vendor split.

### Important: Vue is inlined in the dashboard entry

With a **single** Vue screen, Rolldown/Vite keeps the Vue runtime inside
`dashboard-*.js`. That is intentional and within the pilot budget.

**When a second Vue screen lands**, extract a shared vendor chunk (e.g. `vue`
+ design-system shell) so:

- shared / admin core stays ≤ 60 KiB gzip, and
- per-screen chunks stay ≤ 50 KiB gzip without each re-shipping Vue.

Until then, do **not** treat the empty `admin-*.js` as the “shared Vue”
budget line — the real Vue weight sits on the dashboard row.

---

## How to re-measure

```bash
npm run build
npm run check:bundle-size          # table + soft budget warnings (exit 0)
npm run build:analyze              # build/stats.html treemap (gitignored)
```

| Script | Purpose |
|--------|---------|
| `npm run build` | Typecheck + production build into `build/` |
| `npm run build:analyze` | Same build with `rollup-plugin-visualizer` → `build/stats.html` |
| `npm run check:bundle-size` | Gzip sizes vs budgets; **soft** (warn only). `FAIL_HARD=1` to exit 1 |

Update this document’s baseline table whenever a new heavy entry ships or a
budget row is added. CI runs `check:bundle-size` after the JS build (soft gate).

---

## Debt / follow-ups

| Item | Owner | Notes |
|------|-------|-------|
| Shared Vue vendor chunk | Frontend, with second Vue screen | See note above |
| Hard CI fail on budget | Optional | Flip soft script with `FAIL_HARD=1` once sizes are stable across machines |
| Dashboard REST payload caching | Later | Unrelated to JS weight; see PROGRESS “unbounded queries” |
