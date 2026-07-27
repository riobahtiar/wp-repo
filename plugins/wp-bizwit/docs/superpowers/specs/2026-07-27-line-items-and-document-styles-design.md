# Line item kinds/periods + synchronized document styles

**Status:** Draft for review  
**Date:** 2026-07-27  
**Package:** `wp-bizwit`  
**Version target:** post-1.2.0 (additive schema + presentation)

## Problem

1. **Faktur lines are free-text only.** `bizwit_invoice_items` has `description`, `quantity`, `unit`, prices/tax — no model for goods vs service vs digital, or one-time vs monthly/quarterly/yearly/custom length. Indonesian UMKM invoices routinely mix jasa, barang, and langganan; the unit field alone is not enough.

2. **Document styling is unbalanced across paths.** Layout templates, gallery themes, legacy invoice fallback, and kwitansi each invent type sizes, ink colours, and spacing. Mix of pt/px, body 10.5pt vs legacy 11pt, ink `#1a2332` vs `#1d2327`, compact type below 9px, signature/bank metrics diverge. Screen preview and paper should feel like the same document family.

## Goals

- Flexible **item kind + billing period** on every invoice line, without forcing a product catalog.
- Print stays familiar Indonesian paperwork: **no new table columns**; enrich description/unit.
- **One shared type scale and spacing rhythm** for all document paths; themes keep personality.
- Backward compatible: existing lines and custom templates keep working.
- Period is **labeling only** — never auto-multiplies price by months.

## Non-goals

- Product/service master catalog / SKUs (may come later; leave room, do not build).
- Changing invoice money math, tax, withholding, or status machine.
- New gallery themes or new header layout types.
- Forcing rewrite of user-custom templates that have no theme slug.
- Pixel-perfect clone of any specific Word/Excel template.

---

## Part A — Line item kind & period

### A.1 Data model (additive)

Table: `{prefix}bizwit_invoice_items`  
Migration: bump `Installer::DB_VERSION` from `1.6.0` to **`1.7.0`** via existing `dbDelta` path.

| Column | SQL | Default | Notes |
|--------|-----|---------|--------|
| `item_kind` | `varchar(20) NOT NULL` | `''` | `goods` \| `service` \| `digital` \| `''` (unspecified) |
| `billing_period` | `varchar(20) NOT NULL` | `one_time` | `one_time` \| `monthly` \| `quarterly` \| `yearly` \| `custom` |
| `period_count` | `smallint unsigned NOT NULL` | `0` | Meaningful when `billing_period = custom` (e.g. `6`) |
| `period_unit` | `varchar(10) NOT NULL` | `''` | `day` \| `week` \| `month` \| `year` when custom; else empty |

Existing columns unchanged: `description`, `quantity`, `unit`, `unit_price_minor`, `tax_rate`, `line_total_minor`, `sort_order`.

**Invariants**

- Empty `item_kind` is valid (legacy / “don’t care”).
- `billing_period = one_time` → `period_count = 0`, `period_unit = ''`.
- `billing_period = custom` → `period_count >= 1`, `period_unit` one of the four units.
- Non-custom recurring periods ignore count/unit on save (normalize to 0 / `''`).
- Whitelist all enums in repository sanitize; never trust raw POST.

### A.2 Domain helpers

Small pure helper (e.g. `Support\Line_Item_Meta` or methods on an existing support class):

- `kinds(): array` — slug → label (i18n).
- `periods(): array` — slug → label (i18n).
- `period_units(): array` — day/week/month/year labels.
- `sanitize_kind( $raw ): string`
- `sanitize_period( $raw ): string`
- `sanitize_custom_length( $count, $unit ): array{count:int,unit:string}`
- `suggest_unit( string $period, int $count, string $unit ): string` — preset satuan when appropriate.
- `format_period_label( ... ): string` — human phrase for subline (`berlangganan bulanan`, `6 bulan`, …).
- `format_kind_label( string $kind ): string`
- `enrich_description_html( array $item ): string` — main description + optional muted subline (escaped).
- `display_unit( array $item ): string` — stored unit, else derived preset.

Labels ship in **English source + id_ID** (Anda tone; domain words: Jasa, Barang, Produk digital, bulanan, kuartalan, tahunan, sekali bayar).

### A.3 Persistence

`Invoice_Repository::sanitize_items` / `replace_items`:

- Accept new keys from POST/`$data`.
- Persist four new columns on INSERT.
- `get_items` returns them (empty defaults for pre-migration safety).
- Prefill-from-project: `item_kind ''`, `billing_period one_time`, unit as today.
- Locked (non-draft) invoices: fields read-only like other line inputs.

**Totals:** `Invoice_Totals::calculate` unchanged — kind/period never affect `line_base` or tax.

### A.4 Admin UI (`invoices-form.php` line repeater)

Per row layout (progressive, not noisy):

1. Description (required) — full width.
2. Row of compact controls: **Kind** | **Period** | (if custom: **Count** + **Unit**).
3. Existing money row: Qty · Satuan · Unit price · Tax%.

Behaviour:

- Changing period **suggests** `unit` only if unit is empty or equals a known preset (`pcs`, `jam`, `paket`, `/bulan`, `/kuartal`, `/tahun`, `/N bulan`, etc.). Never overwrite a custom satuan the user typed.
- Suggested units: one_time → leave or `pcs`; monthly → `/bulan`; quarterly → `/kuartal`; yearly → `/tahun`; custom → `/N {unit label}` (e.g. `/6 bulan`).
- Kind does not force unit.
- Blank description still drops the row on save.
- One trailing empty row for add-next (existing pattern).
- Lightweight JS in admin bundle (or small inline consistent with current form) for show/hide custom length + unit suggest — no full Vue rewrite of the invoice editor.

### A.5 Print / PDF / public share

**No new columns** on `table.wp-bizwit-lines`.

| Column | Display |
|--------|---------|
| Description | Primary: `description`. Optional second line (muted, smaller): `{Kind label} · {period phrase}` when kind set and/or period ≠ one_time. If only one_time and no kind → single line (legacy look). |
| Unit (Satuan) | `display_unit(item)` |
| Qty / prices | Unchanged |

Apply in:

- `Layout_Renderer::render_line_items`
- `Document_Blocks` line-items block (same helper)
- Legacy `invoices-form` print fallback `invoices-print.php` if still used

Sample/preview items in renderer/builder should include at least one service + recurring example so themes preview the subline.

### A.6 i18n

- All new UI strings via `__()` / `esc_html__()` text domain `wp-bizwit`.
- Update `languages/wp-bizwit-id_ID.po` (+ build mo/l10n) in the same change.
- Pass `i18n:check-id` gate.

### A.7 Tests

- Unit: sanitize enums, custom length normalization, `suggest_unit` / `display_unit` / label formatting.
- Repository: create invoice with mixed kinds/periods; reload asserts columns.
- Totals regression: identical money with/without kind/period set.
- Optional: HTML fragment contains subline for recurring service, absent for bare one_time goods.

---

## Part B — Synchronized document styles

### B.1 Principle

**Themes own personality** (palette, `fontFamily`, `tableStyle`, `headerStyle`).  
**The system owns rhythm** (type scale, weights, page pads, section distances, table/bank/sign metrics).

Single CSS source of truth: `Document_Styles::css()` CSS variables.  
PHP theme factories and Vue builder defaults consume the **same numeric ramp** via constants on `Document_Styles` (or a tiny `Document_Type_Scale` helper colocated under `Documents\`) readable when seeding layouts — one list of numbers, not three.

### B.2 Page tokens

| Token | Value |
|-------|-------|
| Page size | A4 |
| `--doc-pad-x` | **10mm** (from layout `marginMm`, default 10) |
| `--doc-pad-y` | **12mm** |
| Sheet padding bottom (screen) | **16mm** |
| Sheet padding bottom (print) | **14mm** |
| `@page` margin | **8mm** |

`marginMm` drives **left/right** on print sheets (as wired in 1.2.x). Builder canvas should **match the sheet**: padding `var(--doc-pad-y) marginMm mm bottom` (or equivalent), not equal mm on all sides, so preview ≈ print.

### B.3 Colour tokens (base)

| Token | Value |
|-------|-------|
| `--doc-ink` | `#1a2332` |
| `--doc-muted` | `#5c6570` |
| `--doc-line` | `#e2e6ea` |
| `--doc-accent` | theme (`#1e4d6b` classic default) |
| `--doc-soft` | theme |
| `--doc-total-bg` | theme |

**Remove drift:** legacy prints and `Layout_Renderer` defaults must not use `#1d2327` or other third greys for body ink.

### B.4 Type scale

Use **pt in CSS**. Component props in layout JSON stay **px integers** (builder UX); factories map roles → px on this ramp (1px ≈ 0.75pt at 96dpi is wrong for print — treat stored px as “design px” matching current renderer `font-size: Npx`, but **role targets** below are the visual intent; factories retune to values that read correctly at 10mm pad on A4).

Practical ramp for layout component `fontSize` (px) and CSS (pt):

| Role | CSS | Layout fontSize (px) | Weight |
|------|-----|----------------------|--------|
| Document title | 18pt | 22–24 | 700 |
| Section heading (Bill to, Payment) | 9pt | 9 | 700 · uppercase · tracked · accent/muted |
| Body / field value | **10pt** base body | 11 | 400 |
| Emphasis (client name) | 11–12pt | 12–13 | 600–700 |
| Meta / labels | 8.5–9pt | 9–10 | 600 · muted |
| Table header | 8pt | — (CSS) | 600 · uppercase |
| Table body | 9.5pt | — | 400 |
| Dense table body | **9pt floor** | — | 400 |
| Bank dt / method chrome | 8–9.5pt | — | 600–700 |
| Bank account | 11pt mono | — | 700 |
| Terbilang / notes / sign | 9pt | — | 400 (terbilang italic) |
| Void banner | 14pt | — | 700 |

**Hard floor:** no component or table text below **9** after sanitize (raise compact outliers). Sanitize `fontSize` min stays 9; stop seeding 8.

**Body base** in `Document_Styles`: **10pt**, line-height **1.5**, unified font stack fallback before theme `fontFamily`.

**Legacy** `invoices-print.php` / `payments-print.php`: body **10pt**, ink `#1a2332`, same pad tokens — not 11pt / `#1d2327`.

### B.5 Spacing rhythm

| Element | Value |
|---------|--------|
| Header section | padding-bottom 5mm · margin-bottom 7mm |
| Body section | margin-bottom 4mm |
| Footer section | margin-top 8mm · padding-top 5mm |
| Table cell pad | ~2.2mm × 2.4mm; dense ~1.8 × 2.0mm |
| Default component `marginBottom` | **6** |
| Default component `marginTop` | **0** |
| After line_items before totals | 8–10 |
| Bank block vertical | consistent with CSS (not one-off per theme) |
| Signature `marginTop` | **20–24** |
| Sign boxes | min-height **36mm**, gap **16mm** (unify legacy 34mm / 20mm) |
| Columns `gap` default | **24** (builder default was 28 → 24) |
| Spacer default height | **16** |

### B.6 Theme retune rules

For each of: classic, modern, professional, minimal, elegant, compact:

1. Keep `theme_tokens()` palette + table/header style + fontFamily.
2. Rewrite factory component fontSize/margins onto the ramp (title may sit at top of title band; meta never below 9).
3. Compact: denser **spacing and tableStyle only**; type floor 9.
4. Professional band header: keep bleed using `--doc-pad-x`; inner band padding stays on token.
5. Bump gallery pack option (`GALLERY_OPTION` v6 → **v7**) so `refresh_all_theme_layouts()` rewrites themed posts.
6. Classic default template with theme slug `classic` continues to refresh as today.

User templates **without** a known theme slug: untouched.

### B.7 Path checklist

| Path | Work |
|------|------|
| `Document_Styles.php` | Token block, body 10pt, table/bank/sign metrics, dense floor |
| `Layout.php` | Factory retune; empty/default marginMm 10; sanitize floors |
| `Layout_Renderer.php` | Defaults: fontSize ~11, color ink, mb 6; divider/sign colours from tokens |
| `TemplateBuilderApp.vue` | `defaultProps` + empty margin aligned |
| `Document_Renderer.php` | Already sets `--doc-pad-x`; ensure theme vars complete |
| `invoices-print.php` | Match base tokens (or shared CSS partial if clean) |
| `payments-print.php` | Same base as invoice family |
| Gallery seed | v7 refresh |

### B.8 Success criteria (visual)

- Classic + one gallery theme + kwitansi printed/PDF: related family, readable hierarchy, no hairline-small type, no huge empty header bands.
- Screen white-sheet preview ≈ print dialog inner margins (10mm L/R).
- Recurring service line shows muted subline; bare goods one-time does not.
- PHPCS / PHPStan clean; id_ID gate green; existing money tests still pass.

---

## Architecture sketch

```
Admin form (kind, period, custom length, unit suggest)
        │
        ▼
Invoice_Repository::sanitize_items ──► bizwit_invoice_items (+4 cols)
        │
        ▼
Document_Context items[]
        │
        ├─► Line_Item_Meta::enrich_description_html / display_unit
        │         │
        │         ▼
        │   Layout_Renderer / Document_Blocks / legacy print table
        │
        └─► Invoice_Totals (unchanged)

Document_Styles tokens ◄── theme page CSS vars (Document_Renderer)
        ▲
Layout factories + Vue defaults (same ramp)
```

## Risks & decisions (closed)

| Topic | Decision |
|-------|----------|
| Kind vs period | Two fields (not one flat enum) |
| Print shape | Enrich description + unit; no new columns |
| Price × months | Never automatic |
| Style depth | Shared tokens + retune all themes |
| Catalog | Out of scope |
| Custom templates | No forced overwrite without theme slug |

## Closed implementation choices

| Topic | Choice |
|-------|--------|
| Helper class | `WP_BizWit\Support\Line_Item_Meta` |
| Legacy/kwitansi CSS | Extract shared base rules to `Document_Styles::base_document_css()` (or equivalent) and include from layout CSS + legacy views; avoid long-term duplicated token blocks |
| Unit-suggest JS | Extend existing admin entry (`resources/admin/…`), no new bundle |
| DB version | `1.7.0` |
| Gallery pack | `v7` |

## Implementation order (for later plan)

1. Schema + Installer 1.7.0 + helper + repository + tests.  
2. Admin form + id_ID strings.  
3. Print enrichment in Layout_Renderer / blocks / legacy.  
4. Document_Styles token pass + renderer/builder defaults.  
5. Retune six theme factories + gallery v7.  
6. Legacy invoice + kwitansi alignment.  
7. Smoke: create mixed lines, print each theme, print kwitansi.

---

## Approval

Design agreed in conversation (kind+period, enrich description, shared tokens + all themes).  

**Please review this file and confirm** before the implementation plan is written.
