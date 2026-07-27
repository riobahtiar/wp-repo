# Changelog

All notable changes to WP BizWit are recorded here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Pre-1.0 means the schema can still change.** Until 1.0.0, a minor version bump
> may include a migration. See [plans/PROGRESS.md](plans/PROGRESS.md) for what is
> actually built and [plans/](plans/) for what is coming.

## [Unreleased]

## [1.1.2] — 2026-07-27

Searchable bank select (Tom Select) for payment destinations.

### Changed
- Bank / VA pickers use **Tom Select** with type-ahead search (name or transfer
  code), optgroup headers, and a search field in the dropdown. Native
  `<select>` remains as the progressive-enhancement fallback without JS.
- Version **1.1.2**.

## [1.1.1] — 2026-07-27

Indonesian bank catalogue for payment destination bank / VA pickers.

### Added
- **Bank list** from [data-bank-indonesia](https://github.com/riobahtiar/data-bank-indonesia)
  (137 banks, transfer codes) bundled as `data/indonesia-banks.json`.
- Settings bank/VA fields use optgroup selects (BUMN, private, regional, …)
  with official names and 3-digit codes; free-text “other bank” still available.
- Print lines show bank name plus transfer code when known.
- `npm run data:banks` to refresh the catalogue; `IndonesiaBanksTest`.

### Changed
- Version **1.1.1**.

## [1.1.0] — 2026-07-27

Multiple payment destinations on invoices (not only one bank account).

### Added
- **Payment destinations** in Settings: bank transfer, Virtual Account, payment
  link, DANA, GoPay, OVO, ShopeePay, offline payment, and other — multiple cards,
  optional labels, show/hide on invoices.
- Print/merge field `bank_block` renders all enabled destinations with type
  headings; styles for multi-method blocks on A4 documents.
- Legacy single `bank_*` settings stay in sync with the first bank-transfer
  destination for backward compatibility.
- PHPUnit: `PaymentDestinationsTest`.

### Changed
- Version **1.1.0**. Onboarding checklist asks for any payment method, not only bank.

## [1.0.0] — 2026-07-27

First stable release. Schema freeze (additive migrations only). Security
self-review closed; dashboard performance tooling and expanded tests.

### Added
- **Security self-review** document (`docs/security-self-review.md`) against
  SECURITY.md checklist.
- **Upgrade guide** (`docs/upgrade.md`) and schema stability promise.
- **PHPUnit:** `SequenceTest`, `InstallerUpgradeTest`, `ScreenAuthTest`,
  `MassAssignmentTest` (orderby whitelist, caps, atomic numbering).
- **Dashboard benchmark** CLI: `scripts/benchmark-dashboard.php` (cold/warm timings).
- A11y: region landmarks and table captions on dashboard panels.

### Changed
- Version **1.0.0**. Pre-1.0 “schema may change freely” note retired; see
  upgrade guide for additive-only policy.
- SECURITY.md status: 1.0 GA; support matrix updated.

### Fixed
- Indonesian catalogue: plugin description and remaining activity/dashboard
  strings.

## [0.9.0] — 2026-07-27

Security and performance RC: audit trail, dashboard cache, index for ageing.

### Added
- **Activity audit trail** (`bizwit_activity`): who created/updated/deleted
  clients, projects, invoices and payments, with short summaries. Written from
  repository actions (not screens). Retention **365 days** with daily prune.
  Surfaces as **Recent activity** on the dashboard.
- **Dashboard stats cache**: aggregate tiles and ageing use a short-lived
  transient (120s), busted on every business write and overdue cron pass.
- Composite index `status_due` on invoices for ageing / open-balance queries.
- Schema version **1.5.0**. PHPUnit: `ActivityRepositoryTest`.

### Changed
- Version **0.9.0** (RC toward 1.0).

## [0.8.2] — 2026-07-27

Document print preview language and print-dialog spacing.

### Fixed
- **Print / print-preview language** follows the active WordPress locale:
  document chrome (`Bill to`, table headers, signatures) is translated via
  `Document_I18n`, builder preview strings are injected from PHP, and
  document dates use the regional formatter.
- **Browser print dialog** no longer strips sheet padding (content looked
  edge-to-edge). Screen preview, print dialog and Save as PDF share the same
  inner margins and tighter internal spacing for tables, bank block and
  signatures.

### Changed
- Version **0.8.2**. Indonesian catalogue filled for studio UI strings.

## [0.8.1] — 2026-07-27

Document studio polish: print quality and layout builder UX.

### Changed
- **Print stylesheet** redesigned for A4 Indonesian paperwork (kop accent,
  table header, totals card, bank callout, signature blocks).
- **Template builder** becomes a document studio: Design / Print preview
  modes, sample data on canvas, refined palette and property controls.
- **Default invoice layout** re-tuned for clearer hierarchy and spacing.
- Version **0.8.1**.

## [0.8.0] — 2026-07-27

Test coverage expansion and accessibility hardening for admin screens.

### Added
- **PHPUnit:** `ClientRepositoryTest`, `LayoutTest`, `OnboardingTest`,
  `StatsRepositoryTest` (ageing buckets, delete guards, layout sanitize/render).
- **Skip link** to main BizWit content; notices use `role=alert|status` and
  receive focus after PRG redirects.
- **Dirty-form guard** (progressive enhancement) on product forms.
- **Visible `:focus-visible`** styles for controls inside `.wp-bizwit`.
- Empty states announce with `role="status"`.

### Changed
- Version **0.8.0**.

## [0.7.0] — 2026-07-27

Onboarding and dashboard UX: setup checklist, empty states, receivables ageing.

### Added
- **Dismissible setup checklist** on the dashboard (per user): business name,
  bank details, first client, first invoice, document template.
- **Receivables ageing** (current / 1–30 / 31–60 / 61+ days) for users with
  report capability.
- **Recent invoices** list and **quick actions** (add client, invoice, project,
  record payment).
- **Empty states** with primary actions on clients, projects, invoices and
  payments lists.

### Changed
- Dashboard “getting started” copy updated for the full product path.
- Version **0.7.0**.

## [0.6.2] — 2026-07-27

Document **layout builder** (Vue) replaces the plain Gutenberg edit experience.

### Added
- **A4 canvas builder** on Template edit: component palette, Header/Body/Footer
  zones, and a properties panel per component.
- Components: heading, text, data field, line items, totals, bank, signature,
  spacer, divider, columns — each with style controls (size, weight, colour,
  alignment, margins, toggles).
- Layout stored as JSON (`_wp_bizwit_layout`); print uses `Layout_Renderer`
  (labels still follow the site language).

### Changed
- Template CPT no longer uses the block editor for layout; Vue builder is the
  design surface. Gutenberg document blocks remain for legacy content.
- Version **0.6.2**.

## [0.6.1] — 2026-07-27

Gutenberg document Template designer (Header / Body / Footer) integrated with invoices.

### Added
- **Template CPT** under BizWit → **Template**: design documents with the block
  editor. Each template has Header, Body and Footer sections.
- **Document blocks** (`wp-bizwit/*`): section, merge field, line items, totals,
  signature. Field **labels** re-translate at print time via site locale.
- **Default invoice template** seeded once (sample layout with business, client,
  lines, totals, bank, signature).
- **Invoice print** uses the default Gutenberg template when available; falls
  back to the legacy PHP view otherwise.

### Changed
- Version **0.6.1**.

## [0.6.0] — 2026-07-27

Payments and kwitansi: record money that already arrived, settle invoices, print receipts.

### Added
- **Payments** ([plan 03](plans/03-payments-receipts.md)): list with client/method
  filters; record/edit form; delete with invoice recalculation.
- **`Payment_Repository`**: bank amount + withheld (PPh 23) both count toward
  settlement; `invoices.paid_minor` always recomputed from payments.
- **Invoice status sync**: partial / paid (and overdue when still open past due)
  via `Invoice_Repository::apply_settlement()`.
- **Receipt numbering** on its own `Sequence` key (`receipt:YYYY`).
- **Printable kwitansi** (A4 HTML): terbilang, method, invoice ref, meterai
  reminder above Rp 5.000.000 when enabled.
- Schema **1.4.0**: `withheld_minor`, `withholding_ref` on payments.
- Links: **Record payment** from a non-draft invoice; prefill from invoice
  balance / invoice withholding.
- Overpayment allowed with a credit warning.
- Tests: `PaymentRepositoryTest`.

### Changed
- Version **0.6.0**. Payments placeholder replaced with full recording UI.

## [0.5.0] — 2026-07-27

Invoices end-to-end: list, editor, status machine, print, PPN/PPh 23 rules.

### Added
- **Invoices** ([plan 02](plans/02-invoices.md)): `WP_List_Table` list with
  status filters and overdue view; add/edit form with line-item repeater;
  draft → sent → partial / paid / overdue / void transitions.
- **`Invoice_Repository`** with items replace-all, server-side totals, void
  (number preserved), draft-only delete, create-from-project prefill.
- **`Invoice_Totals`** calculator (line base → header discount → tax scaling →
  withholding). Posted totals are never trusted.
- **`Invoice_Status`** transition rules; editable only while draft.
- **Printable A4 HTML** (browser print-to-PDF): kop fields, lines, bank
  details, terbilang, signature/cap space, meterai note when applicable.
- **PPN gated** on `Settings::charges_sales_tax()` (PKP only); **PPh 23**
  withholding with gross / withheld / net expected.
- **Numbering:** provisional `DRAFT-…` until first leave-draft; permanent
  number via `Sequence` + `Settings::document_number()`.
- **Daily cron** `wp_bizwit_mark_overdue_invoices` to flip past-due open
  invoices to overdue.
- Schema **1.3.0**: `withholding_rate`, `withholding_minor` on invoices.
- Tests: `InvoiceTotalsTest`, `InvoiceRepositoryTest`.
- `Money::line_base_minor()` / `percent_of()` fixed-point helpers.

### Changed
- Version **0.5.0**. Placeholder invoices screen replaced with full CRUD.
- **i18n: English source + full Indonesian catalogue** (carried from unreleased
  work). Domain terms (NPWP, PKP, termin, …) stay official in both languages.
- **JS i18n follows Gutenberg:** `import { __ } from '@wordpress/i18n'`, Vite
  externalizes to core `wp.i18n`, `wp_set_script_translations()`, Jed JSON.

## [0.4.0] — 2026-07-27

Projects CRUD with termin stages, plus the Vue/Vite frontend foundation closed
for feature consumption (Plugin Check still deferred to RC).

### Added
- **Projects** ([plan 01](plans/01-projects.md)): list (`WP_List_Table` with
  search, status and client filters), add/edit form, statuses (active / on hold /
  completed / cancelled), billing types (fixed / hourly / termin / retainer).
- **`bizwit_project_terms` table** and project columns `retensi_percent`,
  `terms_sum_override` (schema `1.2.0`).
- **Termin stages** on the project form (PHP rows): ordered name/amount/notes;
  sum must match budget unless override is checked.
- **Delete guard** when invoices reference the project; bulk cancel and delete.
- **Client edit screen** lists that client's projects with links and “add project”.
- Region labels for anggaran, termin, retensi, SPK / project code (Indonesia).
- **`ProjectRepositoryTest`**: minimal create, invoice delete guard, termin sum
  validation and override.
- **Frontend foundation** (plan 07 Phases 0–8, handoff Phase 9): Vite 8 + Vue 3 +
  Tailwind v4, `Admin\Assets`, REST health, design-system seed (`@ui/*`),
  dashboard pilot, performance baselines, wordpress.org packaging notes.
  Plugin Check deferred to RC.

### Changed
- **Monorepo packaging.** Plugin lives in `wp-repo` (`wp-content` git root). JS
  installs from the monorepo root only (single `package-lock.json`, npm
  workspaces + Turborepo).
- **Vite is the sole asset pipeline.** Shared product CSS in
  `resources/styles/admin.css`; release CI always rebuilds frontend assets.
- Schema version option `wp_bizwit_db_version` → `1.2.0`.

## [0.3.0] — 2026-07-27

Made the plugin usable by people who are not companies.

### Added
- **Business type** setting — individual (freelancer, home business,
  professional) or registered entity. Controls which fields show by default
  without ever making one unreachable.
- **"Not handling tax"** as the default tax status. Users with no tax obligation
  now see no tax field anywhere — not on settings, not on the client form.
- Progressive disclosure across the client form and settings, built on native
  `<details>` so it works with keyboard, screen readers, find-in-page and print.
  Collapsed summaries show current state.
- WhatsApp links on phone numbers in the clients list, since that is where the
  conversation about an unpaid invoice actually happens.
- `plans/` — working documents for unbuilt features, with a progress tracker.
- `SECURITY.md` — threat model, controls, and how to report a vulnerability.
- `docs/culture.md` — designing for Indonesian users beyond tax rules.
- This changelog.

### Changed
- A client now requires only a name. Address, tax identity and billing terms are
  collapsed by default.
- Phone is labelled "Telepon / WhatsApp" in the Indonesian profile and promoted
  above client type on the form.
- Settings reorganised: the basics visible, everything else in labelled sections.
- **Version reset to 0.3.0 from 1.0.0.** Three of six admin screens are
  placeholders and the schema may still change; calling that 1.0 was wrong.

## [0.2.0] — 2026-07-27

Indonesia became the primary profile rather than a supported region.

### Added
- **Regional profile layer** (`src/Localization/`) — `Region` base,
  `Indonesia`, `Generic_Region`, `Regions` resolver. Region is deliberately
  separate from interface language.
- **Indonesian profile**: NPWP (15-digit masked, 16-digit NIK-based), NIB, NIK,
  PKP status, legal entity forms, RT/RW, kelurahan, kecamatan, satker, all 38
  provinces.
- **Tax rules, not just wording**: only a PKP may charge PPN; PPh Final UMKM
  0.5% modelled as a tax on the seller's turnover, never added to an invoice;
  PPh 23 withholding configurable; bea meterai above Rp 5.000.000.
- `Terbilang` — amounts spelled out in Indonesian, handling the irregular
  `sebelas`, `seratus`, `seribu` forms.
- Indonesian document numbering: `007/INV/BW/VII/2026`.
- Complete Indonesian (`id_ID`) translation — 240 strings.
- `clients.meta` JSON column for region-specific paperwork fields.
- `IndonesiaRegionTest` covering NPWP handling, provinces, terbilang,
  numbering, meterai thresholds and the PKP gate.

### Changed
- **IDR is now zero-decimal.** ISO 4217 gives it two (sen), but sen has not
  circulated for decades and no Indonesian invoice shows them.
- Number grouping and decimal separators come from the region, not the site
  locale, so rupiah never renders as `1,500,000`.
- Schema version 1.1.0.

### Fixed
- `phpcs.xml` was excluding every file. The boilerplate's `*.ts` exclude-pattern
  is an unanchored regular expression matching any path containing "ts" —
  including a checkout under `~/Projects/` — so PHPCS was exiting 0 having
  scanned nothing.
- `Money::to_minor()` parsed `1.500.000` as `150`: PHP's float cast truncates at
  the first separator. Multi-separator grouping is now detected before parsing.

## [0.1.0] — 2026-07-26

Initial foundation, scaffolded from
[juvo/wordpress-plugin-boilerplate](https://github.com/JUVOJustin/wordpress-plugin-boilerplate).

### Added
- Seven custom tables with a versioned installer that runs on `plugins_loaded`,
  so a plugin updated in place still migrates.
- `Database\Sequence` — race-free document numbering via a single
  `INSERT ... ON DUPLICATE KEY UPDATE` with `LAST_INSERT_ID(expr)`.
- Money stored as integer minor units in `*_minor` columns.
- Six purpose-built capabilities and two roles, so managing clients does not
  require `manage_options`.
- Full clients CRUD: list table with search, status filters, sorting and bulk
  archive/delete; add/edit form; post/redirect/get.
- Delete guard refusing to orphan projects, invoices or payments.
- Dashboard, settings, and honest placeholder screens for projects, invoices and
  payments.
- `MoneyTest` locking in amount parsing across locale formats.

---

Version links are omitted until this plugin has a published remote. Add
`[0.3.0]: <url>` style definitions here once one exists.
