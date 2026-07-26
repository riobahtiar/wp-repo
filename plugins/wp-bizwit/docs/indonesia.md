# The Indonesian Profile

WP BizWit's default and primary regional profile. This document is the reference
for what it changes and why.

> **Not tax advice.** Rates and thresholds change with the annual budget and with
> a business's own circumstances. Everything below is a *default the business can
> override in Settings*. Confirm what applies to you with your own tax
> consultant. Regulation references are given so you can check the source, not to
> assert that a rate is current.

---

## Why a profile rather than translation

Translating "Tax ID" to "NPWP" would be a language change. What Indonesian
paperwork actually needs is different from that:

- A **field that does not exist** in the generic model (NIB, NIK, PKP status,
  kelurahan, kecamatan, satker).
- A **rule that changes the arithmetic** (a non-PKP business may not charge PPN
  at all; PPh 23 means the client pays less than the invoice total).
- A **format nobody else uses** (`007/INV/BW/VII/2026`, `Rp 1.500.000`,
  `26 Juli 2026`, amounts written out as terbilang).

None of that follows from the interface language, so it is driven by
`Localization\Regions::current()` instead. See [index.md](index.md#a-distinction-worth-getting-right-up-front).

## How the profile is chosen

`BizWit → Settings → Business region`:

- **Detect from business details** (default) — Indonesia when the business
  country is `ID` **or** the currency is `IDR`. Empty settings also resolve to
  Indonesia, so a fresh install is Indonesian immediately. A business invoicing
  in rupiah is doing Indonesian paperwork whatever address it entered.
- **Indonesia** — forced.
- **International (generic)** — forced neutral.

```php
use WP_BizWit\Localization\Regions;
use WP_BizWit\Localization\Indonesia;

$region = Regions::current();

if ( $region instanceof Indonesia ) {
    // Indonesian-only UI or logic.
}
```

Call `Regions::reset()` after changing settings. `Settings::save()` already does.

---

## Client identity

| Field | Stored in | Notes |
|-------|-----------|-------|
| NPWP | `clients.tax_id` | 15-digit legacy or 16-digit NIK-based |
| NIB | `clients.registration_no` | 13 digits, from OSS |
| NIK | `clients.meta` | 16 digits |
| Status PKP | `clients.meta` | Whether the client can receive a faktur pajak |
| Bentuk badan usaha | `clients.meta` | PT, PT Perorangan, CV, Firma, UD, Koperasi, Yayasan, Perkumpulan, BUMN, BUMD, Instansi, Perorangan |
| Satuan kerja | `clients.meta` | For government clients: the satker issuing the SPK or contract |

Core columns are **relabelled**, not duplicated — `tax_id` is simply *called*
NPWP in this region. Genuinely extra fields go in the JSON `meta` column so a
future second-country profile does not widen the table for everyone. See
[data-model.md](data-model.md).

### NPWP handling

Since the NIK-based migration (PMK 112/2022) individual taxpayers use their
16-digit NIK as their NPWP. Older client records and printed contracts still
carry 15-digit numbers, so both are accepted:

```php
$region->format_tax_id( '012345678901234' );  // '01.234.567.8-901.234'
$region->format_tax_id( '1234567890123456' ); // '1234567890123456'
$region->is_valid_tax_id( '12345' );          // false
```

A 15-digit NPWP is stored in its conventional masked form, because that is how
it appears on every document a client will compare it against. A 16-digit one is
stored as plain digits, which is how Coretax presents it. Anything that is
neither length is rejected at save time with an explanatory `WP_Error`.

### Client types

The four stored slugs never change; only their labels do.

| Slug | Indonesian label |
|------|------------------|
| `individual` | Perorangan |
| `company` | Perusahaan (PT / CV / UD) |
| `government` | Instansi Pemerintah |
| `organization` | Yayasan / Koperasi / Organisasi |

Do not add a fifth type for a country. If a new entity kind is needed, it is a
`legal_form` meta value.

## Address

Indonesian addresses are more granular than the generic model:

```
Jl. Gatot Subroto No. 45      → address_line1
Gedung / unit                 → address_line2
RT 003 / RW 005               → meta.rt_rw
Kelurahan Kuningan Barat      → meta.kelurahan
Kecamatan Mampang Prapatan    → meta.kecamatan
Jakarta Selatan               → city   (labelled "Kabupaten / Kota")
DKI Jakarta                   → state  (labelled "Provinsi", dropdown)
12930                         → postal_code
ID                            → country
```

All **38 provinces** are offered, including the four created in the 2022 Papua
expansion: Papua Selatan, Papua Tengah, Papua Pegunungan and Papua Barat Daya.

---

## Tax

This is where the profile stops being cosmetic.

### Status perpajakan

Set once in Settings; everything downstream follows.

| Regime | Meaning | Effect on invoices |
|--------|---------|--------------------|
| `umkm_final` | PPh Final UMKM 0,5% (PP 55/2022) — **default** | No PPN line |
| `non_pkp` | Not registered for PPN | No PPN line |
| `pkp` | Pengusaha Kena Pajak | PPN charged at the configured rate |

```php
Settings::charges_sales_tax();   // false unless the regime is PKP
Settings::effective_tax_rate();  // '0' unless the regime is PKP
```

Saving a non-PKP regime **forces the stored rate to `0`**, rather than leaving a
non-zero rate sitting in the database for whoever builds the first invoice to
trip over.

**Why this matters.** Only a PKP may collect PPN. A small business that puts PPN
on an invoice without being registered is billing tax it has no right to collect
— a real compliance problem, not a cosmetic one. Registration is generally
required once annual gross turnover exceeds **Rp 4.800.000.000**
(`Indonesia::PKP_THRESHOLD`).

### PPh Final UMKM

0,5% of gross turnover under PP 55/2022. It is a tax on **your own** turnover,
not a charge added to the client's bill, so BizWit will never put it on an
invoice. It is recorded as your status so the rest of the plugin knows not to
charge PPN.

### PPN

Default 11%, editable. The rate structure has been unusually fluid — the general
rate moved to 11% under UU HPP 7/2021, and the subsequent 12% headline rate is
applied to a different tax base for non-luxury goods, which changes the effective
result. **Check the current position with your tax consultant** and set the field
accordingly; BizWit deliberately does not hardcode a rule here.

### PPh 23 withholding

A corporate client withholding PPh 23 on services pays you the invoice total
**minus** the withheld amount and remits that portion to the state on your
behalf. Common rate 2% where the vendor has an NPWP (UU PPh 36/2008).

An invoice model that ignores this will not reconcile: the bank shows less than
the invoice says. `withholding_label` and `withholding_rate` in Settings exist so
the invoice screen can record the difference when it is built.

### Bea meterai

Rp 10.000 on documents stating an amount above Rp 5.000.000 (UU 10/2020), which
in practice catches most kwitansi.

```php
$region->stamp_duty( 5000000, 'IDR' ); // 0
$region->stamp_duty( 5000001, 'IDR' ); // 10000
```

---

## Money and dates

**Rupiah is zero-decimal in BizWit.** ISO 4217 formally gives IDR two decimals
(sen), but sen has not circulated for decades and no Indonesian invoice,
kwitansi or bank statement shows them. Storing whole rupiah keeps stored values
the same magnitude as the numbers people type and read.

> Changing this would reinterpret every stored `*_minor` value and requires a
> data migration. It is not a config flag.

Separators come from the region, never the site locale — a rupiah figure written
`1,500,000` reads as one and a half to an Indonesian reader:

```php
Money::format( 1500000, 'IDR' );      // 'Rp 1.500.000'
Money::to_minor( 'Rp 2.750.000' );    // 2750000
$region->format_date( '2026-07-26' ); // '26 Juli 2026'
```

Month names are produced by the profile rather than `date_i18n()`, so dates stay
Indonesian even when the site runs in another locale.

### Terbilang

Indonesian kwitansi carry the amount written out in words. It is not decoration:
the written form is what makes a signed receipt hard to alter afterwards.

```php
Money::in_words( 1500000, 'IDR' ); // 'Satu juta lima ratus ribu rupiah'
```

`Localization\Terbilang` handles the irregular forms a digit-by-digit conversion
gets wrong: **sebelas** not "satu belas", and the `se-` contraction in
**sepuluh**, **seratus**, **seribu** rather than "satu ratus" / "satu ribu".

## Document numbering

Indonesian document numbers are composite, so the month and year can be read
straight off the number:

```
007 / INV / BW / VII / 2026
 │     │     │    │     └── year
 │     │     │    └──────── month in roman numerals
 │     │     └───────────── business code (Settings → Business code)
 │     └─────────────────── document type: INV faktur, KW kwitansi
 └───────────────────────── sequence, padded
```

```php
Settings::document_number( 'invoice', 7, '2026-07-26' ); // '007/INV/BW/VII/2026'
Settings::document_number( 'receipt', 1, '2026-01-05' ); // '001/KW/BW/I/2026'
```

Set **Number format → Simple prefixed sequence** for `INV-0007` instead.

Numbers are allocated by `Database\Sequence` using a single
`INSERT ... ON DUPLICATE KEY UPDATE` with `LAST_INSERT_ID(expr)`, so two people
saving at the same moment can never be handed the same number.

## Payment methods

How Indonesian clients actually pay:

Transfer Bank · Tunai · QRIS · E-Wallet (GoPay / OVO / DANA / ShopeePay) ·
Virtual Account · Cek / Giro · Kartu Kredit / Debit · SP2D (instansi pemerintah)
· Kompensasi / Potongan · Lainnya

BizWit **records** these. It never initiates any of them.

## Business scale

Classification under PP 7/2021 — Usaha Mikro, Kecil, Menengah, Besar — based on
business capital or annual sales. Recorded in Settings so the tax regime default
and the thresholds shown to the user make sense for the business.

---

## Glossary

| English | Indonesian | Notes |
|---------|-----------|-------|
| Invoice | Faktur / Tagihan | Faktur *pajak* is the specific VAT document a PKP issues |
| Receipt | Kwitansi | Carries terbilang; often needs meterai |
| Tax ID | NPWP | 15 or 16 digits |
| National ID | NIK | 16 digits; now also the individual NPWP |
| Business registration | NIB | 13 digits, from OSS; replaced SIUP and TDP |
| VAT | PPN | Only a PKP may charge it |
| VAT-registered business | PKP | Pengusaha Kena Pajak |
| Withholding tax | PPh 23 | On services; client withholds and remits |
| Stamp duty | Bea meterai | Rp 10.000 above Rp 5.000.000 |
| Amount in words | Terbilang | Required in practice on kwitansi |
| Payment terms | Termin pembayaran | Commonly 14 or 30 days |
| Installment / milestone | Termin | Termin 1, 2, 3 on staged projects |
| Down payment | Uang muka / DP | |
| Retention | Retensi | Common in construction |
| Work order | SPK | Surat Perintah Kerja |
| Handover minutes | BAST | Berita Acara Serah Terima; usually required before invoicing |
| Government work unit | Satker | Satuan Kerja |
| Government disbursement | SP2D | How instansi pemerintah actually pay |
| Small and medium business | UMKM | Usaha Mikro, Kecil dan Menengah |
| Village / sub-district | Kelurahan / Desa | |
| District | Kecamatan | |
| Regency / city | Kabupaten / Kota | |
| Province | Provinsi | 38 of them |

## Adding another region

Extend `Localization\Region`, implement `code()`, `label()`, `client_types()` and
`document_number()`, override whatever else applies, and register the class in
`Regions::all()`. Everything else — labels, meta fields, provinces, tax
formatting, stamp duty — has a neutral default in the base class.

Keep the four client type slugs. Put jurisdiction-specific fields in
`meta_fields()`, never in new columns.
