## AI Coding Agent Instructions for WP BizWit

This plugin is a modern WordPress plugin with strict conventions and automated
workflows. Follow these guidelines.

`CLAUDE.md` in this directory is a **symlink to this file**.

---

## Monorepo context (required reading)

| | |
|---|---|
| **Git root** | `wp-content/` (repo **wp-repo** → `git@github.com:riobahtiar/wp-repo.git`) — **not** this package alone, **not** WordPress core |
| **This package path** | `plugins/wp-bizwit/` inside that monorepo |
| **Monorepo agent guide** | [`../../AGENTS.md`](../../AGENTS.md) · [`../../README.md`](../../README.md) |
| **Install host guide** | WordPress install root (parent of `wp-content`): `../../../AGENTS.md` for Herd, WP-CLI, security, Indonesia-first defaults |

### Tooling from monorepo root

```bash
cd ../..                                 # → wp-content/ (git root)
npm install                              # ONLY from monorepo root
npm run -w wp-bizwit build
npm run -w wp-bizwit dev
npm run -w wp-bizwit test:unit
npm run bizwit -- check:bundle-size
turbo run build --filter=wp-bizwit

cd plugins/wp-bizwit && composer install # PHP deps stay package-local
```

**Never** run `npm install` only inside this directory (no nested
`package-lock.json`). CI and releases run from monorepo root
(`.github/workflows/` there only — see [`.github/README.md`](.github/README.md)).

This file covers **BizWit-specific** domain and conventions. Monorepo layout and
npm workspaces rules live one level up; install-wide WP rules two levels up.

**Longer-form documentation lives in [`docs/`](docs/)** — read it before making
non-trivial changes:

| Document | Read it before |
|----------|----------------|
| [`docs/indonesia.md`](docs/indonesia.md) | Touching any user-facing wording, tax logic, money or document numbering |
| [`docs/culture.md`](docs/culture.md) | Designing any form, copy, notification or printable document |
| [`docs/data-model.md`](docs/data-model.md) | Changing the schema or adding a repository |
| [`docs/development.md`](docs/development.md) | Running the tooling, or adding translatable strings |
| [`docs/frontend-architecture.md`](docs/frontend-architecture.md) | **Any interactive admin UI** — Vue/Vite/Tailwind stack, budgets, no Livewire/Acorn |
| [`SECURITY.md`](SECURITY.md) | **Any change that touches data.** Contains the review checklist |
| [`plans/`](plans/) | Starting work on an unbuilt feature — the plan states the traps |
| [`plans/07-frontend-modernization.md`](plans/07-frontend-modernization.md) | Implementing the frontend foundation (phased tasks) |
| [`plans/PROGRESS.md`](plans/PROGRESS.md) | Checking what is actually built before assuming |

---

## What this plugin is

Business administration for the person running the business: **clients,
projects, invoices, and payment records**, all inside wp-admin.

**Indonesian companies and UMKM are the primary target.** Indonesia is the
default regional profile and IDR the default currency; a fresh install already
speaks the right vocabulary without anyone finding a setting. Other regions are
supported through the same abstraction, but Indonesian correctness wins any
design argument.

That priority reaches past tax rules into how the software behaves for the
people using it. Full detail in [`docs/culture.md`](docs/culture.md); the parts
that bite most often:

- **Never require a surname.** Many Indonesians have a single legal name. A
  mandatory "last name" field locks out a large share of the population. This is
  why `display_name` and `legal_name` are single fields.
- **Name fields carry titles** — `Drs. H. Bambang Suryanto, S.E., M.M.` is one
  person's formal name. Do not strip punctuation or use short column widths.
- **Copy uses `Anda`, never `kamu`**, and prefixes requests with `Silakan` or
  `Mohon`. Indonesian business UI is politer than English business UI, and the
  markers are not optional.
- **Domain terms stay Indonesian** even in an English interface — faktur,
  kwitansi, NPWP, PPN, termin, meterai. Translating them invents words nobody
  uses.
- **Phone is not optional metadata.** Indonesian B2B runs on WhatsApp; documents
  are forwarded as PDFs. Accept `08xx` and `+62` without mangling either.
- **Printable output needs room for a signature, the company cap and meterai**,
  plus the bank transfer details, on A4.
- **Money is large and dot-grouped**: `Rp 1.500.000`. Nine digits is normal, so
  do not size columns or inputs for four.
- **Lebaran moves everything.** Expect one to two weeks where collections stop.
  Do not treat a long outstanding period as an error state, and never hardcode a
  holiday calendar.

**Hard scope boundary: this plugin never processes, moves, or holds money.** It
is a record-keeping system. There is no gateway integration, no card handling, no
payment initiation. "Payments" here means *recording that a payment already
happened somewhere else* and issuing the matching receipt. Any request that would
add payment processing is a scope change, not a feature — say so before building it.

### Client types

One `clients` table covers all four entity types via a `type` column:
`individual`, `company`, `government`, `organization`. They differ in which
fields are *relevant*, not in which fields exist, and all four bill identically.
Do not split them into separate tables or post types.

---

## Domain model

Custom tables, not custom post types. Invoices have line items, running balances,
and numbers that must be unique — none of which `postmeta` can express or index
usefully. All tables are prefixed `{$wpdb->prefix}bizwit_`.

| Table | Holds | Notes |
|-------|-------|-------|
| `bizwit_clients` | Every billable entity | `type`, `status`, address, currency, payment terms |
| `bizwit_client_contacts` | Named people at a client | `is_primary` flag |
| `bizwit_projects` | Work for a client | `billing_type`: fixed / hourly / milestone / retainer |
| `bizwit_invoices` | Invoice headers | Unique `invoice_number`; statuses draft / sent / partial / paid / overdue / void |
| `bizwit_invoice_items` | Line items | `quantity` and `tax_rate` are `DECIMAL`, not float |
| `bizwit_payments` | Recorded payments, which double as receipts | Unique `receipt_number` |
| `bizwit_sequences` | Atomic document-number counters | Natural primary key on `sequence_key` |

Defined in `src/Database/Schema.php`. To change the schema: edit `Schema`, then
**bump `Installer::DB_VERSION`** — nothing migrates without that.

### Rules the code depends on

- **Money is always an integer in the currency's minor unit.** Columns are named
  `*_minor` to make violations obvious at a glance. `Support\Money` is the only
  place allowed to convert to or from a human-readable amount, so rounding
  happens exactly once, at the edge. Never do arithmetic on a formatted string.
- **Derived values are not stored.** An invoice balance is
  `total_minor - paid_minor`, computed on read. A stored balance is a second
  source of truth that drifts.
- **There are no foreign keys.** `dbDelta()` cannot parse them and the storage
  engine may not support them. Referential integrity is enforced in the
  repositories — see `Client_Repository::delete()`, which refuses to delete a
  client that still has projects, invoices, or payments and returns a `WP_Error`
  explaining why. Add the same guard to any new entity that owns children.
- **Document numbers come from `Database\Sequence`, never from `MAX() + 1`.**
  `Sequence::next()` allocates via a single `INSERT ... ON DUPLICATE KEY UPDATE`
  using `LAST_INSERT_ID(expr)`, so concurrent saves cannot collide. `peek()` is
  for previewing only — never persist a peeked value.
- **Timestamps are site-local** (`current_time( 'mysql' )`), not GMT. Do not mix.

---

## Regional profiles — read before touching any user-facing wording

**A region is not a language.** Two independent axes:

| | Controlled by | Changes |
|---|---|---|
| **Interface language** | WordPress site locale + `languages/*.mo` | The words wp-admin is written in |
| **Regional profile** | `Localization\Regions`, from settings | Business vocabulary, which fields exist, tax rules, number and date formats, document numbering |

An Indonesian company running wp-admin in English still needs a field labelled
NPWP, a Provinsi dropdown, and kwitansi with the amount in words. Never gate
domain vocabulary on `get_locale()`.

`Regions::current()` resolves the active profile: an explicit setting, else
auto-detection (business country `ID` **or** currency `IDR` → Indonesia; empty
settings → Indonesia). Call `Regions::reset()` after changing settings — use
`Settings::save()`, which does it for you.

### Adding or changing user-facing wording

- Field labels and help text come from `$region->field_label()` /
  `field_description()` with a region-neutral fallback. Do not hardcode a label
  in a view.
- Region-specific *extra* fields come from `$region->meta_fields()` and are
  stored as JSON in `clients.meta`, read back with `Client_Repository::meta()`.
- Client type slugs (`individual`, `company`, `government`, `organization`) are
  fixed; only their labels vary by region. Never add a fifth type for a country.

### Indonesian rules that change what a correct document looks like

These are business rules, not wording. `Localization\Indonesia` owns them.

- **Only a PKP may charge PPN.** `Settings::charges_sales_tax()` and
  `Settings::effective_tax_rate()` are the gate. A non-PKP invoice must never
  carry a PPN line — that is billing tax the business has no right to collect.
  Saving settings with a non-PKP regime forces the stored rate to `0`.
- **PPh Final UMKM 0.5%** (PP 55/2022) is a tax on the seller's own turnover,
  not a charge added to the client's invoice. Never add it to an invoice total.
- **PPh 23** withholding means the client pays the invoice total *minus* the
  withheld amount. An invoice model that ignores this will not reconcile against
  the bank. Rate lives in `withholding_rate`.
- **Bea meterai** Rp 10,000 on documents stating over Rp 5,000,000
  (`Indonesia::stamp_duty()`), which catches most kwitansi.
- **Terbilang.** Indonesian kwitansi carry the amount in words; that is what
  makes them hard to alter after signing. `Money::in_words()` →
  `Localization\Terbilang`. The grammar has irregulars — `sebelas` not `satu
  belas`, `seratus`/`seribu` not `satu ratus`/`satu ribu`.
- **Document numbers** are composite: `007/INV/BW/VII/2026` — sequence / type /
  business code / roman month / year. Built by `Settings::document_number()`.
- **NPWP** is accepted at 15 digits (legacy, stored masked as
  `01.234.567.8-901.234`) or 16 digits (NIK-based, stored plain).
- **IDR is zero-decimal here.** ISO 4217 says 2 (sen), but sen has not
  circulated for decades. Changing this would reinterpret every stored
  `*_minor` value and needs a data migration.

Tax rates change with the annual budget. Treat everything as a *default the
business overrides*, and keep UI wording pointing at the user's own tax
consultant rather than asserting current law.

## Layer conventions

```
src/
  Database/       Schema, Installer (versioned migrations), Sequence
  Localization/   Region (base), Indonesia, Generic_Region, Regions, Terbilang
  Repositories/   All $wpdb access. Repository (base) + one class per entity
  Support/        Money, Settings, Capabilities
  Rest/           REST base + Controllers under wp-bizwit/v1 (health; more later)
  Admin/
    Menu.php      Registers pages, wires each screen's load- hook
    Assets.php    Enqueues Vite entries from build/manifest.json (screen-scoped)
    Screens/      One class per screen, extending Screen
    Tables/       WP_List_Table subclasses
    Views/        Templates. Receive a single $data array — no extract()
```

- **All `$wpdb` access goes through a repository.** That is what keeps the
  `phpcs:ignore` suppressions direct database queries require confined to a few
  audited methods, instead of scattered across screens where a missing
  `prepare()` is easy to miss in review.
- **Repositories sanitise, screens do not.** Pass raw `$_POST` straight to
  `create()` / `update()`; the repository's private `sanitize()` builds the
  column list from a hardcoded array, so an unexpected key can never reach a
  query.
- **Form handling belongs in `Screen::on_load()`**, which runs on `load-{page}`
  before any output. That is what makes `wp_safe_redirect()` possible, giving
  post/redirect/get so a refresh cannot resubmit. Never handle a POST inside
  `render()`.
- **Every screen checks its own capability.** `Screen::render_page()` does this
  for you; do not bypass it.
- **User feedback across a redirect** goes through `Admin\Notices`, which uses a
  per-user transient rather than a query string.

### Capabilities

Six purpose-built capabilities in `Support\Capabilities`:
`bizwit_manage_clients`, `_projects`, `_invoices`, `_payments`,
`bizwit_view_reports`, `bizwit_manage_settings`. Two roles ship with the plugin:
`bizwit_manager` (all) and `bizwit_staff` (clients + projects only).

**Never gate a BizWit screen on `manage_options`.** That is a site-administration
capability; requiring it to add a client would force every bookkeeper to be a
full administrator.

Roles are written to the database on activation only. After changing
`Capabilities::install()`, deactivate and reactivate the plugin.

### Translations

Indonesian (`id_ID`) ships complete: all 240 strings. Workflow after adding or
changing any translatable string:

```bash
$(command -v wp) i18n make-pot . languages/wp-bizwit.pot --exclude=resources,vendor,vendor-prefixed,node_modules,tests
$(command -v wp) i18n update-po languages/wp-bizwit.pot languages/
# translate the new msgids in languages/wp-bizwit-id_ID.po, then
$(command -v wp) i18n make-mo languages/ && $(command -v wp) i18n make-php languages/
```

Use the global `wp` explicitly — see the tooling papercuts below. Compiled
`.mo` / `.l10n.php` files are gitignored and built during release; regenerate
them locally or Indonesian will not appear.

Note that strings authored in Indonesian in `Localization\Indonesia` are still
wrapped in `__()` and appear in the catalogue translated to themselves. That is
deliberate: it keeps one extraction pipeline and lets a future `en_US` override
render those screens in English if anyone wants it.

---

## Current state

Built: schema for all seven tables, versioned installer, capabilities and roles,
admin menu, dashboard, **full Clients CRUD** (list table with search / filter /
sort / bulk archive and delete, add/edit form), settings, the regional profile
layer, and a complete Indonesian translation.

Placeholders: Projects, Invoices, Payments screens render an honest "not built
yet" panel. Their tables already exist, so implementing them needs no migration.
`Repositories/Stats_Repository.php` already queries them for the dashboard.

Tests:

- `tests/php/MoneyTest.php` — amount parsing across locale formats. Extend it
  before touching `Money::normalize_decimal_string()`: a separator misread by one
  position is an invoice wrong by a factor of a hundred, and nothing downstream
  catches it.
- `tests/php/IndonesiaRegionTest.php` — NPWP formatting and validation, the 38
  provinces, terbilang grammar, document numbering, bea meterai thresholds, and
  the PKP / non-PKP tax gate.

## Known tooling papercuts in this checkout

- **`composer run i18n:extract` fails with `'i18n' is not a registered wp command`.**
  Composer prepends `vendor/bin` to `PATH`, so `wp` resolves to the bare WP-CLI
  *framework* pulled in as a dev dependency rather than the global WP-CLI phar
  that bundles the i18n commands. Run it against the global binary instead:

  ```bash
  $(command -v wp) i18n make-pot . languages/wp-bizwit.pot --exclude=resources,vendor,vendor-prefixed,node_modules,tests
  ```

- **PHPStan OOMs at its default 128M limit** in this codebase. Always pass
  `--memory-limit=1G`. The symptom is `Child process error (exit code 255)`
  from a parallel worker, which is not a code problem.

- **`phpcs.xml` scopes by PHP extension** and anchors directory excludes with
  `type="relative"` and a leading `^`. PHPCS exclude patterns are unanchored
  regexes; a naive `*.ts` exclude can match any path containing "ts" (including
  `~/Projects/`) and silently scan nothing. If `phpcs` exits 0 with no findings,
  run `./vendor/bin/phpcs -v` and check the "files in queue" count.

---

### Ownership and dependencies

This plugin is **self-contained and first-party**. It is not tied to an external
plugin boilerplate and must not be "synced" from one. Patterns under `src/`
(Loader, Activator, repositories, screens) are **owned here** and may be
changed freely.

**Production `composer.json` `require` should stay empty** unless a library
clearly earns its place. When a runtime PHP library is added, prefix it into
`vendor-prefixed/` with Strauss so it cannot clash with other plugins.

Dev tools (PHPStan, WPCS, PHPUnit, Vite, Vitest, `@wordpress/env`) may use
Composer/npm packages that are widely used, actively maintained (updated within
~8 months), and from known maintainers — see
[`docs/development.md`](docs/development.md#dependency-policy).

### Architecture & Source Layout

- **All plugin logic lives in `src/`**. Organize by feature/context (e.g., `Admin/`, `Database/`, `Repositories/`).
- **Main plugin file (`wp-bizwit.php`) only bootstraps**. Never place business logic here.
- **Loader pattern**: Register hooks, filters, shortcodes and CLI commands via `Loader`. Do not register hooks in constructors.

### Asset management

Interactive product UI uses **Vue 3 + Vite 8 + Tailwind v4**. Full decisions:
[`docs/frontend-architecture.md`](docs/frontend-architecture.md). Implementation:
[`plans/07-frontend-modernization.md`](plans/07-frontend-modernization.md).

- **Do not** add Livewire, Roots Acorn, or a second SPA framework to this plugin.
- **Assets**: `resources/admin/`, `resources/screens/`, `resources/ui/`,
  `resources/styles/` → monorepo `npm run -w wp-bizwit build` → `build/` (Vite
  sole owner; manifest enqueued from PHP via `Admin\Assets`).
- **Shared admin entry** loads on every BizWit screen (styles + tiny JS). Screen
  islands (e.g. dashboard) are extra entries only on their page.
- **Scope CSS** under `.wp-bizwit` so Tailwind never restyles core wp-admin.
- **Enqueue by screen** for heavy islands — never load the invoice bundle on every BizWit page.

**Design system (`resources/ui/`):** import shared components via the `@ui`
alias (resolved in `vite.config.ts` / `tsconfig.json`):

```ts
import Button from '@ui/Button.vue';
import MoneyText from '@ui/MoneyText.vue';
import EmptyState from '@ui/EmptyState.vue';
import AppShell from '@ui/AppShell.vue';
```

Money display uses integer minor units and `formatMoney` from
`@/app/lib/money` (mirror of PHP `Support\Money` edge rules for UI only).
Unit tests from monorepo root: `npm run -w wp-bizwit test:unit` (Vitest).

### Quality Assurance

- **PHP**: PHPStan (`phpstan.neon`), PHPCS (`phpcs.xml`) — after package
  `composer install`
- **JS/TS**: `vue-tsc` / Vitest via monorepo (`npm run -w wp-bizwit …`)
- **CI/CD**: monorepo root `.github/workflows/` only
  ([`.github/README.md`](.github/README.md))
- **npm**: monorepo root only — no nested `package-lock.json`

### Key primitives

**Loader methods:** `add_action()`, `add_filter()`, `add_shortcode()`, `add_cli()`

**Composer scripts:** `phpstan`, `phpcs`, `phpcbf`, `i18n:extract`, `i18n:compile`

**NPM package scripts** (invoke as `npm run -w wp-bizwit <name>` from monorepo
root): `dev`, `build`, `build:assets`, `build:analyze`, `check:bundle-size`,
`typecheck`, `test:unit`, `env:*`, `test:php`

### Feature quick reference

- **Blocks** (optional): none ship today. `WP_BizWit::register_blocks()` no-ops
  unless `build/Blocks` + `build/blocks-manifest.php` exist;
  `tests/php/BlockRegistrationTest.php` skips when empty.
- **i18n**: Indonesian (`id_ID`) is a shipped, complete translation — **an
  untranslated new string is unfinished**. Prefer global `wp` for extract/compile;
  see [`docs/development.md`](docs/development.md#translations).
- **wp-env / PHPUnit**: from monorepo root —
  `npm run -w wp-bizwit env:start` · `npm run -w wp-bizwit test:php`.
- **Testing**: Prefer repository and domain tests (`MoneyTest`,
  `IndonesiaRegionTest`, `RestHealthTest`); extend those before changing money,
  region, or REST permission logic.

### Maintaining the plugin

When adding new primitives, patterns, or documentation to this plugin:

1. Update `docs/` with detailed implementation guides
2. Update @AGENTS.md with high-level reference
3. Add the Indonesian translation for any new user-facing string
4. If the change touches wording, tax logic, money or documents, check it against
   [`docs/indonesia.md`](docs/indonesia.md) before considering it done
5. Do not reintroduce an external boilerplate dependency or scaffold sync path

### The two rules that override everything else here

1. **Indonesian companies and UMKM are the primary users.** When a design choice
   has to favour one audience, it favours Indonesia. Other regions are supported
   through `Localization\Region`, not by diluting the default.
2. **BizWit never processes, moves, or holds money.** It records payments that
   happened elsewhere. A request to add payment processing is a change of
   product, not a feature — raise it as one.
