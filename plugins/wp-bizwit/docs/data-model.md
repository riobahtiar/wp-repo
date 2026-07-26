# Data Model

Seven custom tables, all prefixed `{$wpdb->prefix}bizwit_`. Defined in
`src/Database/Schema.php`.

## Why custom tables rather than custom post types

CPTs would have bought a free admin UI, but at a cost the domain cannot absorb:

- **Invoices have line items.** A one-to-many relationship modelled in
  `postmeta` becomes serialised arrays that no query can reach into.
- **Money must be indexed and summed.** `postmeta.meta_value` is `LONGTEXT`;
  summing revenue means casting strings in a full scan.
- **Invoice numbers must be unique.** `postmeta` cannot carry a `UNIQUE` index,
  so nothing at the database level stops two invoices sharing a number.
- **Reporting joins clients to projects to invoices to payments.** That is a
  relational question, and `postmeta` answers it with one self-join per field.

The cost is that the admin UI is hand-built on `WP_List_Table`. That is a known,
bounded amount of work; a reporting layer fighting `postmeta` is neither.

## Tables

| Table | Holds |
|-------|-------|
| `bizwit_clients` | Every billable entity: individuals, companies, government bodies, organisations |
| `bizwit_client_contacts` | Named people at a client, with an `is_primary` flag |
| `bizwit_projects` | Work for a client; `billing_type` fixed / hourly / milestone / retainer |
| `bizwit_invoices` | Invoice headers; unique `invoice_number` |
| `bizwit_invoice_items` | Line items; `quantity` and `tax_rate` are `DECIMAL` |
| `bizwit_payments` | Recorded payments, which double as receipts; unique `receipt_number` |
| `bizwit_sequences` | Atomic document number counters, natural key on `sequence_key` |

## Rules the schema depends on

### Money is an integer in the currency's minor unit

Every monetary column is a signed `BIGINT` named `*_minor`. The naming makes a
violation visible at a glance.

Binary floating point cannot represent `0.10` exactly, so summing invoice lines
as floats accumulates error that eventually shows up as an invoice that will not
reconcile against its payments. `Support\Money` is the only code allowed to cross
between minor units and a human-readable amount, so rounding happens exactly once
— at the edge — and never mid-calculation.

Note that for IDR the minor unit **is** the rupiah. See
[indonesia.md](indonesia.md#money-and-dates).

### Derived values are not stored

An invoice balance is `total_minor - paid_minor`, computed on read. A stored
balance is a second source of truth, and it will drift the first time a payment
is edited outside the happy path.

### There are no foreign keys

`dbDelta()` cannot parse `FOREIGN KEY` clauses, and the storage engine is not
guaranteed to support them. Referential integrity is enforced in the repository
layer instead.

`Client_Repository::delete()` is the reference implementation: it refuses to
delete a client that still has projects, invoices or payments, and returns a
`WP_Error` explaining that the client should be archived instead. Any new entity
that owns children needs the same guard.

### Region-specific fields live in JSON

`clients.meta` holds a JSON object of fields that only some jurisdictions have —
NIK, PKP status, kelurahan, kecamatan, satker. They are printed on documents,
never filtered or sorted on, and giving each country its own columns would widen
the table for every user who does not operate there.

```php
$meta = Client_Repository::meta( $client );
$is_pkp = ! empty( $meta['is_pkp'] );
```

Only keys the active region declares in `meta_fields()` are ever written, so
switching region cannot let an unexpected field through.

### Timestamps are site-local

`current_time( 'mysql' )`, not GMT. Business records are read and reasoned about
in the site's timezone. Do not mix the two.

## Migrations

`src/Database/Installer.php` runs `dbDelta()` for every statement and stores
`DB_VERSION` in the `wp_bizwit_db_version` option. The check runs on
`plugins_loaded`, not only on activation — **activation hooks do not fire when a
plugin is updated in place**, so an activation-only installer leaves updated
sites on a stale schema.

To change the schema:

1. Edit `Schema.php`.
2. **Bump `Installer::DB_VERSION`.** Nothing migrates without this.
3. Load any admin page. `dbDelta()` will add columns and indexes.

`dbDelta()` parses your SQL with regular expressions rather than a real parser,
so the formatting is load-bearing:

- One column per line.
- Two spaces after `PRIMARY KEY`: `PRIMARY KEY  (id)`.
- `KEY`, never `INDEX`.
- Lowercase type names.
- No `FOREIGN KEY` clauses.

`dbDelta()` never drops columns or tables. Removing something is a manual
migration step.

## Document numbering

`bizwit_sequences` exists so numbers can be allocated atomically:

```sql
INSERT INTO wp_bizwit_sequences (sequence_key, next_value)
VALUES (%s, LAST_INSERT_ID(1))
ON DUPLICATE KEY UPDATE next_value = LAST_INSERT_ID(next_value + 1)
```

`SELECT MAX(number) + 1` is a lost-update bug: two people saving in the same
second read the same maximum and mint the same number. The row lock taken by
`INSERT ... ON DUPLICATE KEY UPDATE` serialises callers, and `LAST_INSERT_ID(expr)`
returns the freshly computed value through the connection's insert id, so
allocation and read are a single uninterruptible round trip.

`Sequence::peek()` previews the next number for a form. **Never persist a peeked
value** — another user may consume it before the form is submitted.

## Access control

Six capabilities in `Support\Capabilities`: `bizwit_manage_clients`,
`_projects`, `_invoices`, `_payments`, `bizwit_view_reports`,
`bizwit_manage_settings`. Two roles ship with the plugin: `bizwit_manager` (all)
and `bizwit_staff` (clients and projects only).

**Never gate a BizWit screen on `manage_options`.** That is a site-administration
capability; requiring it to add a client would force every bookkeeper to be a
full administrator.

Roles are written to the database on activation only. After changing
`Capabilities::install()`, deactivate and reactivate the plugin.
