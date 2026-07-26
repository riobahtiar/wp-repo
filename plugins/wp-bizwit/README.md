# WP BizWit

**WordPress Plugins for Business and Administration.**

A lightweight back office inside wp-admin: client records, project billing,
invoices and payment receipts.

> **Record keeping only.** WP BizWit never processes, moves, or holds money.
> "Payments" means recording a payment that already happened elsewhere and
> issuing the matching receipt. There is no gateway integration by design.

Built on the [JUVO WordPress Plugin Boilerplate](https://github.com/JUVOJustin/wordpress-plugin-boilerplate).

---

## What it manages

| | |
|---|---|
| **Clients** | Individuals, companies, government entities and other organisations, with legal name, tax ID, registration number, address, currency and payment terms |
| **Projects** | Work per client, billed fixed-price, hourly, by milestone or on retainer |
| **Invoices** | Line items, tax, discounts, gap-free sequential numbering |
| **Payments** | Recorded receipts against invoices, including partial payments |

## Design decisions worth knowing

**Custom tables, not custom post types.** Invoices have line items, running
balances and numbers that must be unique. Modelling that in `postmeta` means
unindexed string comparisons for monetary values and no way to enforce
uniqueness on an invoice number.

**Money is stored as integer minor units.** Binary floating point cannot
represent `0.10` exactly, so summing invoice lines as floats accumulates error
that eventually surfaces as an invoice that will not reconcile. Every monetary
column is a `BIGINT` named `*_minor`, and `Support\Money` is the only code
allowed to convert to or from a human-readable amount.

**Document numbers are allocated atomically.** `SELECT MAX(number) + 1` is a
lost-update bug: two concurrent saves read the same maximum and mint the same
number. `Database\Sequence` uses a single `INSERT ... ON DUPLICATE KEY UPDATE`
with `LAST_INSERT_ID(expr)` so the read-modify-write cannot be interleaved.

**Purpose-built capabilities, not `manage_options`.** A bookkeeper should not
need to be a site administrator to add a client. Two roles ship with the plugin:
BizWit Manager and BizWit Staff.

**Nothing is deleted without being asked twice.** Deleting a client that still
has projects, invoices or payments is refused with an explanation — archive it
instead. Uninstalling only drops data if you opt in from Settings first.

---

## Development

Requires PHP 8.0+, Composer, and Node.

```bash
composer install
npm install
npm run build
```

| Command | Purpose |
|---------|---------|
| `npm run start` | Rebuild assets on change |
| `npm run build` | Production asset build |
| `./vendor/bin/phpcs` | WordPress Coding Standards |
| `./vendor/bin/phpcbf` | Auto-fix coding standard violations |
| `./vendor/bin/phpstan analyse --memory-limit=1G` | Static analysis at level 6 |
| `npm run test:php` | PHPUnit suite in the wp-env container |
| `composer run i18n:extract` | Regenerate the translation template |

PHPStan's parallel workers exhaust the default 128M limit, hence the explicit
`--memory-limit`. A `Child process error (exit code 255)` is that, not a code
problem.

## Layout

```
src/
  Database/       Schema, versioned Installer, atomic Sequence
  Repositories/   All $wpdb access lives here
  Support/        Money, Settings, Capabilities
  Admin/          Menu, Screens, WP_List_Table subclasses, view templates
resources/        SCSS and JS entry points, compiled by @wordpress/scripts
tests/php/        PHPUnit tests
```

See [`AGENTS.md`](AGENTS.md) for the conventions this codebase relies on.

## Status

Clients are fully implemented. Projects, invoices and payments have their schema
in place and render a placeholder screen; no migration is needed when their
interfaces land.

## License

GPL-2.0-or-later.
