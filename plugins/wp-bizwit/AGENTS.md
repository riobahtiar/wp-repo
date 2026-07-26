## AI Coding Agent Instructions for WP BizWit
This plugin is a modern WordPress plugin with strict conventions and automated workflows. Follow these guidelines.

For install-wide WordPress conventions (security rules, `dbDelta` gotchas, WP-CLI
patterns, the Herd/Dbngin environment), see the `CLAUDE.md` at the WordPress root.
This file covers what is specific to WP BizWit.

---

## What this plugin is

Business administration for the person running the business: **clients,
projects, invoices, and payment records**, all inside wp-admin.

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

## Layer conventions

```
src/
  Database/       Schema, Installer (versioned migrations), Sequence
  Repositories/   All $wpdb access. Repository (base) + one class per entity
  Support/        Money, Settings, Capabilities
  Admin/
    Menu.php      Registers pages, wires each screen's load- hook
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

### Currency note

IDR is treated as a **2-decimal** currency, per ISO 4217. Indonesian invoices are
conventionally written in whole rupiah, so if that becomes a problem, add `IDR`
to `Money::ZERO_DECIMAL_CURRENCIES` — but note that doing so changes the meaning
of every already-stored `*_minor` value and needs a data migration.

---

## Current state

Built: schema for all seven tables, versioned installer, capabilities and roles,
admin menu, dashboard, **full Clients CRUD** (list table with search / filter /
sort / bulk archive and delete, add/edit form), settings.

Placeholders: Projects, Invoices, Payments screens render an honest "not built
yet" panel. Their tables already exist, so implementing them needs no migration.
`Repositories/Stats_Repository.php` already queries them for the dashboard.

Tests: `tests/php/MoneyTest.php` locks in amount parsing. Extend it before
touching `Money::normalize_decimal_string()` — a separator misread by one
position is an invoice wrong by a factor of a hundred, and nothing downstream
catches it.

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

- **`phpcs.xml` was changed from the boilerplate default.** Upstream excludes
  `*.js`, `*.ts` and `*.tsx` by pattern, but PHPCS exclude patterns are
  unanchored regular expressions: `*.ts` compiles to `.*.ts` and matches any
  absolute path containing "ts" — including a checkout under `~/Projects/`,
  which silently excluded every file and made `phpcs` exit 0 having scanned
  nothing. This plugin scopes by `<arg name="extensions" value="php"/>` and
  anchors directory excludes with `type="relative"` and a leading `^`. If you
  sync `phpcs.xml` from upstream, re-apply that fix and verify with
  `./vendor/bin/phpcs -v` that the "files in queue" count is non-zero.

---

### Architecture & Source Layout

- **All plugin logic lives in `src/`**. Organize by feature/context (e.g., `Admin/`, `Frontend/`, `Integrations/`).
- **Main plugin file (`wp-bizwit.php`) only bootstraps**. Never place business logic here.
- **Loader pattern**: Register hooks, filters, shortcodes, CLI commands, and abilities via the `Loader` class. Do not register hooks in constructors.

### Asset Management

- **Assets**: Place in `resources/admin/` and `resources/frontend/`.
- **Build**: `@wordpress/scripts` handles compilation. Entry points in `webpack.config.js`.
- **Scripts**: `npm run start` (watch), `npm run build` (production).

### Quality Assurance

- **PHP**: PHPStan (`phpstan.neon`), PHPCS (`phpcs.xml`)
- **JS**: ESLint (`.eslintrc`)
- **CI/CD**: GitHub Actions in `.github/workflows/`

### Key Primitives

**Loader methods:**
- `add_action()`, `add_filter()` - WordPress hooks
- `add_shortcode()` - Shortcode registration
- `add_cli()` - WP-CLI commands
- `add_ability()` - Abilities API (WP 6.9+)

**Composer scripts:** `phpstan`, `phpcs`, `phpcbf`, `i18n:extract`, `i18n:compile`

**NPM scripts:** `start`, `build`, `lint:js`, `lint:style`, `format`, `create-block`, `env:*`

### Feature Quick Reference

- **Blocks**: Run `npm run create-block`. Registration is automatic; `tests/php/BlockRegistrationTest.php` generically guards that every built block is loaded (via `/wp/v2/block-types`) and its assets exist. Use the `wp-plugin-bp` skill for block guidance.
- **Abilities API**: Implement interfaces in `src/Abilities/`, register via Loader. `tests/php/AbilityRegistrationTest.php` generically guards that every `Ability_Interface` implementation (and its category) is registered. Use the `wp-plugin-bp` skill for ability guidance.
- **i18n**: Extract with `composer run i18n:extract`, compile with `composer run i18n:compile`. Use the `wp-plugin-bp` skill for translation work.
- **wp-env**: Start with `npm run env:start`. Use the `wp-plugin-bp` skill when tests are involved.
- **Testing**: Run application tests with `npm run test:php`. Use the `wp-plugin-bp` skill for testing guidance.
- **Plugin upgrades**: Use the `wp-plugin-bp` skill or ask naturally to sync with upstream project conventions.
- **Official WordPress skills**: `.agents/skills/wp-*/` contains focused skills for block development, Interactivity API, PHPStan, project triage, and REST API work. Use the `wp-plugin-bp` skill `wp-skills` workflow to refresh or add official WordPress skills.
- **Composer setup**: `.agents/` ships in the initial Composer package so setup can run `wp-plugin-bp/scripts/plugin-replace.php`; replacement cleanup removes `.agents/`, then setup asks whether to install agent skills for ongoing work.
- **Missing skills**: If `wp-plugin-bp` is unavailable in an initialized plugin, install it with `npx skills add https://github.com/JUVOJustin/wordpress-plugin-boilerplate --skill=*`.

### Maintaining the plugin

When adding new primitives, patterns, or documentation to this plugin:

1. Update `docs/` with detailed implementation guides
2. Update @AGENTS.md with high-level reference
