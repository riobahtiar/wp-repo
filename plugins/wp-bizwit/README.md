# WP BizWit

**WordPress Plugins for Business and Administration.**

A lightweight back office inside wp-admin: client records, project billing,
invoices and payment receipts.

**Built for Indonesian companies and UMKM first.** Indonesia is the default
profile and rupiah the default currency — NPWP, NIB, PKP status, Provinsi,
kwitansi with terbilang, bea meterai and `007/INV/BW/VII/2026` numbering work out
of the box. Ships with a complete Indonesian (`id_ID`) translation. Businesses
elsewhere get a generic international profile.

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

## Untuk pengguna Indonesia

| | |
|---|---|
| **Identitas klien** | NPWP (15 digit format lama maupun 16 digit berbasis NIK), NIB dari OSS, NIK, status PKP, bentuk badan usaha (PT, PT Perorangan, CV, Firma, UD, Koperasi, Yayasan, BUMN, BUMD, instansi) |
| **Alamat** | Jalan, RT/RW, Kelurahan/Desa, Kecamatan, Kabupaten/Kota, dan 38 provinsi |
| **Perpajakan** | Pilihan status UMKM (PPh Final 0,5%), Non-PKP, atau PKP. PPN hanya muncul bila Anda PKP |
| **Pemotongan** | Pencatatan PPh 23 agar nilai faktur dan dana masuk tetap dapat direkonsiliasi |
| **Dokumen** | Penomoran `007/INV/BW/VII/2026`, kwitansi dengan terbilang, pengingat bea meterai di atas Rp 5.000.000 |
| **Pembayaran** | Transfer bank, tunai, QRIS, e-wallet, virtual account, cek/giro, kartu, SP2D |
| **Format** | Rp 1.500.000 dengan pemisah ribuan titik, tanggal "26 Juli 2026" |

Rujukan tarif dan ambang batas adalah nilai bawaan yang dapat Anda ubah di
Pengaturan. Pastikan ketentuan yang berlaku kepada konsultan pajak Anda.

## Design decisions worth knowing

**A region is not a language.** The `id_ID` translation changes the interface
language; the regional profile changes the business vocabulary, which fields
exist, and the tax rules. An Indonesian company running wp-admin in English
still gets NPWP fields and terbilang on its kwitansi.

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
  Localization/   Region profiles (Indonesia, generic) and Terbilang
  Repositories/   All $wpdb access lives here
  Support/        Money, Settings, Capabilities
  Admin/          Menu, Screens, WP_List_Table subclasses, view templates
resources/        SCSS and JS entry points, compiled by @wordpress/scripts
languages/        Translation template and the id_ID catalogue
tests/php/        PHPUnit tests
```

Compiled `.mo` / `.l10n.php` files are gitignored and built at release time.
After a fresh clone, regenerate them or the Indonesian translation will not
appear:

```bash
$(command -v wp) i18n make-mo languages/ && $(command -v wp) i18n make-php languages/
```

See [`AGENTS.md`](AGENTS.md) for the conventions this codebase relies on.

## Status

Clients are fully implemented. Projects, invoices and payments have their schema
in place and render a placeholder screen; no migration is needed when their
interfaces land.

## License

GPL-2.0-or-later.
