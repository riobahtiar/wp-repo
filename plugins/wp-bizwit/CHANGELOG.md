# Changelog

All notable changes to WP BizWit are recorded here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Pre-1.0 means the schema can still change.** Until 1.0.0, a minor version bump
> may include a migration. See [plans/PROGRESS.md](plans/PROGRESS.md) for what is
> actually built and [plans/](plans/) for what is coming.

## [Unreleased]

### Changed
- **i18n: English source + full Indonesian catalogue.** User-facing strings use
  English msgids (WordPress.org practice). `id_ID` translations cover the
  catalogue; switching the site language switches the whole UI. Domain terms
  (NPWP, PKP, termin, …) stay official in both languages.
- **JS i18n follows Gutenberg:** `import { __ } from '@wordpress/i18n'`, Vite
  externalizes the package to core `wp.i18n`, handles `wp-bizwit-{entry}`,
  `wp_set_script_translations()`, Jed JSON via `npm run i18n:compile`. See
  [docs/i18n.md](docs/i18n.md) and the
  [Gutenberg i18n guide](https://github.com/WordPress/gutenberg/blob/trunk/docs/how-to-guides/internationalization.md).

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
