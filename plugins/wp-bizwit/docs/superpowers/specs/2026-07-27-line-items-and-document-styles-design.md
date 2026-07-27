# Line item kinds/periods + synchronized document styles

**Status:** Implemented (Unreleased; awaiting version bump)  
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
- `period_units(): array` — day/week/month/year: canonical stored terms (`hari`, `minggu`, `bulan`, `tahun`) plus i18n labels for the admin dropdown (see the canonical-strings rule below).
- `sanitize_kind( $raw ): string`
- `sanitize_period( $raw ): string`
- `sanitize_custom_length( $count, $unit ): array{count:int,unit:string}`
- `suggest_unit( string $period, int $count, string $unit ): string` — preset satuan when appropriate; returns canonical terms only (see rule below).
- `format_period_label( ... ): string` — human phrase for subline (`berlangganan bulanan`, `6 bulan`, …).
- `format_kind_label( string $kind ): string`
- `enrich_description_html( array $item ): string` — main description + optional muted subline (escaped).
- `display_unit( array $item ): string` — stored unit, else derived preset.

Labels ship in **English source + id_ID** (Anda tone; domain words: Jasa, Barang, Produk digital, bulanan, kuartalan, tahunan, sekali bayar).

**Canonical vs render-time strings.** Satuan presets (`pcs`, `jam`, `paket`, `satuan`, `hari`, `minggu`, `bulan`, `kuartal`, `tahun`, and generated `{N} {unit}` like `6 bulan`) are **stored data** written to `bizwit_invoice_items.unit`, so they are canonical Indonesian domain terms and are **never gettext-translated on input** — otherwise stored values fork by admin locale and the safe-to-overwrite preset detection breaks. The admin controls may show i18n labels, but the value persisted is always the canonical term. Kind labels and `format_period_label()` output, by contrast, are generated at render time and use ordinary gettext (`__()`), following the same site-locale convention as the rest of the document chrome (table headers, totals); they are never persisted. `Document_I18n::chrome()` is only for strings stored inside layout JSON — the subline is not one of those.

### A.3 Persistence

`Invoice_Repository::sanitize_items` / `replace_items`:

- Accept new keys from POST/`$data`.
- Persist four new columns on INSERT — `replace_items()` gains the four columns plus four `'%s'` / `'%d'` formats (it currently writes 8 columns with 8 formats at `Invoice_Repository.php:1037-1047`).
- `get_items()` is `SELECT *`, so the new keys flow to `Document_Context` automatically; readers still use `??` defaults (`item_kind ''`, `billing_period one_time`, `period_count 0`, `period_unit ''`) for pre-migration safety.
- Prefill-from-project: `item_kind ''`, `billing_period one_time`, unit as today.
- Locked (non-draft) invoices: fields read-only like other line inputs.

**Totals:** `Invoice_Totals::calculate` unchanged — kind/period never affect `line_base` or tax.

### A.4 Admin UI (`invoices-form.php` line repeater)

Per item layout (progressive, not noisy) — this **replaces today's single `<tr>` per item** (`invoices-form.php:318-361` renders one row per item under fixed column headers):

- Keep the `<table class="widefat striped wp-bizwit-items">` shell and its money column headers (Description · Qty · Satuan · Unit price · PPN % · Line total; tax column stays conditional on `charges_sales_tax`, first description stays `required`).
- Each item becomes a **row pair**: the existing money `<tr>` (description + qty + satuan + price + tax) plus a `<tr class="wp-bizwit-item-meta">` carrying the compact **Kind** | **Period** | (if custom: **Count** + **Unit**) controls in a single `colspan` cell, visually muted so it never competes with the money row.
- Removal still means clearing the description; a blank description drops the whole pair on save.
- The existing "one trailing empty row" pattern (`invoices-form.php:71-84`) becomes **one trailing empty pair**; the admin JS clones the pair (both `<tr>` elements) for add-next.

Behaviour:

- Changing period **suggests** `unit` only if unit is empty or equals a known canonical preset (`pcs`, `jam`, `paket`, `satuan`, `hari`, `minggu`, `bulan`, `kuartal`, `tahun`, or a generated `{N} {unit}` like `6 bulan`). Detection compares case-insensitively against the canonical list only — never against translated strings — and never overwrites a custom satuan the user typed.
- Suggested units: one_time → leave unchanged (or `pcs`); monthly → `bulan`; quarterly → `kuartal`; yearly → `tahun`; custom → `{N} {unit}` (e.g. `6 bulan`). Plain forms, **no `/` prefix** — satuan must read correctly next to any quantity on paper ("3 bulan", not "3 /bulan").
- Kind does not force unit.
- Blank description still drops the pair on save.
- One trailing empty pair for add-next (extension of the existing trailing-row pattern).
- Lightweight JS in the existing admin entry (`resources/admin/main.ts`) for show/hide custom length + unit suggest + pair cloning — no full Vue rewrite of the invoice editor.

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
- Legacy print fallback `invoices-print.php` (still the no-template fallback when `Document_Renderer` finds no template post)

Subline labels are produced at render time via gettext (site locale), same as the table headers around them — **not** via `Document_I18n::chrome()` (that mechanism is only for strings persisted inside layout JSON). The id_ID translations ship in the same change (A.6), so Indonesian-locale sites get `Jasa · berlangganan bulanan` on paper.

Sample/preview items: `Document_Renderer::sample_context()` (`Document_Renderer.php:269-286`) gains the new keys with at least one `service` + `monthly` example and one bare `goods` + `one_time` example, so every theme preview and the builder live preview exercise both the subline and the legacy single-line look. The no-context builder placeholder ("Line items table") stays as-is.

### A.6 i18n

- All new UI strings via `__()` / `esc_html__()` text domain `wp-bizwit`.
- Update `languages/wp-bizwit-id_ID.po` (+ build mo/l10n) in the same change.
- Pass `i18n:check-id` gate.

### A.7 Tests

- Unit: sanitize enums, custom length normalization, `suggest_unit` / `display_unit` / label formatting — including locale-independent canonical preset detection.
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

Use **pt in CSS, px in layout JSON props** — and the two columns below must describe the **same visual size**. `Layout_Renderer::text_style()` emits literal `font-size: Npx` and **keeps doing so**: switching the renderer to pt would silently re-tune every user-custom template that has no theme slug (a non-goal). The px column is therefore the exact 96dpi conversion of the pt intent — **`px = round(pt × 4/3)`** — not a "design px" approximation. Component `fontSize` props stay px integers (builder UX); factories and builder defaults seed the px values below.

Practical ramp for layout component `fontSize` (px) and CSS (pt):

| Role | CSS | Layout fontSize (px) | Weight |
|------|-----|----------------------|--------|
| Document title | 18pt | 24 | 700 |
| Section heading (Bill to, Payment) | 9pt | 12 | 700 · uppercase · tracked · accent/muted |
| Body / field value | **10pt** base body | 13 | 400 |
| Emphasis (client name) | 11–12pt | 15–16 | 600–700 |
| Meta / labels | 8.5–9pt | 11–12 | 600 · muted |
| Table header | 8pt | — (CSS) | 600 · uppercase |
| Table body | 9.5pt | — | 400 |
| Dense table body | **9pt floor** | — | 400 |
| Bank dt / method chrome | 8–9.5pt | — | 600–700 |
| Bank account | 11pt mono (down from 11.5pt — intentional) | — | 700 |
| Terbilang / notes / sign | 9pt | — | 400 (terbilang italic) |
| Void banner | 14pt | — | 700 |

**Floors (two, on purpose):** CSS-authored text never below **9pt** (dense table body is the floor). Seeded/factory component `fontSize` never below **11px (≈8pt)**, with 12px (9pt) the norm for readable meta — today's compact seeds at 8px (≈6pt, the hairline outliers at `Layout.php:684-699`) are raised onto the ramp. The sanitize clamp `max( 9, min( 36, … ) )` **stays at 9px** for back-compat: existing user layouts keep their stored values untouched.

**Body base** in `Document_Styles`: **10pt**, line-height **1.5**, unified font stack fallback before theme `fontFamily`.

**Legacy** `invoices-print.php` / `payments-print.php`: body **10pt**, ink `#1a2332`, same pad tokens — not 11pt / `#1d2327`.

### B.5 Spacing rhythm

| Element | Value |
|---------|--------|
| Header section | padding-bottom 5mm · margin-bottom 7mm |
| Body section | margin-bottom 4mm |
| Footer section | margin-top 8mm · padding-top 5mm |
| Table cell pad | ~2.2mm × 2.4mm; dense ~1.8 × 2.0mm |
| Default component `marginBottom` | **6** in all three default sites — `Layout::sanitize()` (4 today), `Layout_Renderer::margin_style()` (4 today), Vue `defaultProps` |
| Default component `marginTop` | **0** |
| After line_items before totals | 8–10 |
| Bank block vertical | consistent with CSS (not one-off per theme) |
| Signature `marginTop` | **20–24** |
| Sign boxes | min-height **36mm**, gap **16mm** — unifies `Document_Styles` (38mm/16mm today) and legacy kwitansi (34mm/20mm); the `::before` 30mm rule stays as the signature-line mechanism |
| Signature spacing owner | Component `marginTop` owns it in the layout path — remove the `margin-top: 12mm` on `.wp-bizwit-sign` in `Document_Styles` (today it double-spaces: 12mm + 24px); the `Document_Blocks` signature keeps CSS-owned spacing |
| Columns `gap` default | **24** (builder default was 28 → 24) |
| Spacer default height | **16** |

### B.6 Theme retune rules

For each of: classic, modern, professional, minimal, elegant, compact:

1. Keep `theme_tokens()` palette + table/header style + fontFamily.
2. Rewrite factory component fontSize/margins onto the ramp (title may sit at top of title band; meta never below 9).
3. Compact: denser **spacing and tableStyle only**; type floor 9.
4. Professional band header: keep bleed using `--doc-pad-x`; inner band padding stays on token.
5. Bump gallery pack v6 → **v7** so `refresh_all_theme_layouts()` rewrites themed posts — note `GALLERY_OPTION` is the option *name* (`wp_bizwit_gallery_templates_v6`, `Default_Templates.php:27`) while the stored/compared *value* is also `'v6'` (lines 65-67); both move to v7.
6. Classic default template with theme slug `classic` continues to refresh as today.

User templates **without** a known theme slug: untouched.

### B.7 Path checklist

| Path | Work |
|------|------|
| `Document_Styles.php` | Token block, body 10pt, table/bank/sign metrics, dense floor |
| `Layout.php` | Factory retune onto the corrected px ramp (seed floor 11px — compact 8px seeds raised); empty/default marginMm 10; sanitize floors + default marginBottom 6 |
| `Layout_Renderer.php` | Defaults: fontSize 13 (body), color ink (`#1d2327` → `var(--doc-ink)`), mb 6; divider/sign colours from tokens; `.wp-bizwit-sign` CSS margin-top removed |
| `TemplateBuilderApp.vue` | `defaultProps` + empty margin aligned to the ramp; canvas padding matches the sheet (`var(--doc-pad-y) marginMm mm bottom`, per B.2) |
| `Document_Renderer.php` | Already sets `--doc-pad-x`; ensure theme vars complete; `sample_context()` gains kind/period example items (A.5) |
| `invoices-print.php` | Match base tokens (or shared CSS partial if clean) |
| `payments-print.php` | Same base as invoice family, **plus** retune the kwitansi-only blocks onto tokens: `.box`, `.amount` (16pt), `table.meta`, `.meterai`, and the legacy `.sign` (34mm/20mm → shared sign metrics) — the shared base CSS alone leaves these bespoke |
| Gallery seed | v7 refresh — `GALLERY_OPTION` option *name* suffix and stored/compared *value* both move v6 → v7 (`Default_Templates.php:27,65-67`) |

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
| Renderer units | Keep `font-size: Npx` in `text_style()`; px ramp column = `round(pt × 4/3)` — no unit switch that would silently re-tune user templates |
| Seeded px floor | 11px (≈8pt) for factories/builder; sanitize min stays 9px for stored user layouts |
| Satuan presets | Canonical Indonesian terms (`bulan`, `kuartal`, `tahun`, …), plain (no `/` prefix), stored untranslated; admin dropdown labels may translate |
| Subline i18n | Render-time gettext (site locale), same as table headers; not `Document_I18n::chrome()` |
| Invoice form structure | Row pair per item (money row + meta row) inside the existing widefat table; trailing empty pair; JS clones pairs |
| Signature spacing | Component `marginTop` owns the layout path; CSS `margin-top: 12mm` removed from `.wp-bizwit-sign` |
| Sample items | `Document_Renderer::sample_context()` gains kind/period examples |

## Implementation order (for later plan)

1. Schema + Installer 1.7.0 + helper + repository + tests.  
2. Admin form + id_ID strings.  
3. Print enrichment in Layout_Renderer / blocks / legacy + `sample_context()`.  
4. Document_Styles token pass + renderer/builder defaults.  
5. Retune six theme factories + gallery v7.  
6. Legacy invoice + kwitansi alignment.  
7. Smoke: create mixed lines, print each theme, print kwitansi.

---

## Approval

Design agreed in conversation (kind+period, enrich description, shared tokens + all themes).  

**Reviewed 2026-07-27 against the 1.2.x codebase; amendments folded in:** corrected px/pt ramp (renderer keeps px; `px = round(pt × 4/3)`), invoice-form row-pair structure, canonical untranslated satuan presets, subline gettext decision, kwitansi-only block retune (`.box`, `.amount`, `table.meta`, `.meterai`), signature spacing ownership, sanitize default alignment, gallery option name+value bump mechanics, named sample-data source (`Document_Renderer::sample_context()`). All factual claims verified against `Installer.php`, `Schema.php`, `Document_Styles.php`, `Layout.php`, `Layout_Renderer.php`, `Document_Renderer.php`, `Default_Templates.php`, `Invoice_Repository.php`, `invoices-form.php`, `invoices-print.php`, `payments-print.php`, `TemplateBuilderApp.vue`.

Ready for the implementation plan.
