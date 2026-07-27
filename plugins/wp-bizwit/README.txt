=== WP BizWit ===
Contributors:
Tags: invoicing, faktur, kwitansi, umkm, indonesia
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WordPress Plugins for Business and Administration. Manage clients, projects, invoices and payment records from wp-admin.

== Description ==

WP BizWit turns a WordPress install into a lightweight back office for a small
business. It keeps the records a business actually runs on: who your clients
are, what work you are doing for them, what you have billed, and what has been
paid.

**Built for Indonesian businesses first.** Indonesia is the default profile and
rupiah the default currency, so a fresh install already speaks the right
vocabulary: NPWP, NIB, NIK, status PKP, bentuk badan usaha, alamat lengkap
sampai kelurahan dan kecamatan, dan seluruh 38 provinsi. Faktur bernomor
007/INV/BW/VII/2026, kwitansi dengan nilai terbilang, dan pengingat bea meterai.
The interface is fully translated into Indonesian.

**Tax rules, not just tax wording.** BizWit knows that only a PKP may charge
PPN, that PPh Final UMKM 0.5% is a tax on your own turnover rather than a line
on your client's invoice, and that a corporate client withholding PPh 23 pays
you less than the invoice total. Set your status once and the paperwork follows.

**Clients of every kind.** Individuals, companies, government bodies and other
organisations are all first-class client types, each with the identity fields
that matter to them: legal name, tax ID, registration number, billing address,
currency and payment terms.

**Projects and billing.** Track work per client with fixed-price, hourly,
milestone or retainer billing.

**Invoices and receipts.** Issue invoices with line items, tax and discounts,
numbered the Indonesian way as 007/INV/BW/VII/2026 or as a simple prefixed
sequence. Record the payments you receive and issue matching kwitansi, complete
with the amount in terbilang.

**Built to keep the numbers right.** Every amount is stored as an integer in the
currency's minor unit rather than as a floating point number, so totals cannot
drift by a cent. Invoice and receipt numbers are allocated atomically in the
database, so two people saving at the same moment can never be handed the same
number.

**Access control that fits the job.** BizWit defines its own capabilities and
ships two roles — BizWit Manager and BizWit Staff — so a bookkeeper does not have
to be a site administrator to add a client.

= What this plugin does not do =

**WP BizWit does not process payments.** There is no payment gateway, no card
handling, and no money ever moves through this plugin. It records payments that
happened elsewhere — a bank transfer, cash, or a card terminal — so your records
match reality. Choose a dedicated payments plugin if you need to take money on
your site.

== Installation ==

1. Upload the `wp-bizwit` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open **BizWit → Settings** and fill in your business details, currency and
   document numbering.
4. Add your clients under **BizWit → Clients**.

Activation creates the plugin's database tables and roles. Deactivating the
plugin never deletes your data. Uninstalling only deletes it if you tick the
option in Settings first.

== Frequently Asked Questions ==

= Can BizWit take payments from my clients? =

No, and it is not intended to. BizWit is a record-keeping system. It records that
a payment was received; it never initiates or processes one.

= Does it use custom post types? =

No. Clients, projects, invoices and payments live in their own database tables.
Invoices need unique numbers, indexed monetary columns and proper line items,
none of which post meta handles well.

= Will uninstalling delete my invoices? =

Only if you explicitly opt in. **BizWit → Settings → Data** has a
"Delete all BizWit data when the plugin is uninstalled" checkbox that is off by
default.

= Which currencies are supported? =

A selection of common currencies, with IDR first. Rupiah is treated as a
zero-decimal currency, since sen has not circulated for decades and no
Indonesian invoice shows them. The list is filterable via `wp_bizwit_currencies`.

= Can I use this outside Indonesia? =

Yes. Set **BizWit → Settings → Business region** to "International (generic)",
or simply set your country and currency and let BizWit detect it. You then get
neutral wording and plain sequential document numbers.

= Are the tax rates in the plugin guaranteed to be current? =

No. Rates and thresholds change with the annual budget, so everything BizWit
ships is a sensible default you can override in Settings. Confirm what applies
to your business with your own tax consultant. BizWit records your paperwork; it
does not give tax advice.

= Apakah plugin ini tersedia dalam bahasa Indonesia? =

Ya. Seluruh antarmuka sudah diterjemahkan. Atur bahasa situs WordPress Anda ke
Bahasa Indonesia melalui Pengaturan → Umum. Istilah bisnis seperti NPWP, PPN dan
kwitansi tetap muncul walaupun antarmuka Anda berbahasa Inggris, karena wilayah
usaha dan bahasa antarmuka diatur terpisah.

== Changelog ==

= 1.0.0 =
* Initial release.
* Client records for individuals, companies, government entities and organisations.
* Database schema, versioned migrations, capabilities and roles.
* Dashboard, clients list and editor, and settings screens.
* Indonesian regional profile: NPWP, NIB, NIK, PKP status, legal entity forms,
  full address fields, all 38 provinces, PPN / PPh Final UMKM / PPh 23 handling,
  bea meterai, terbilang, and Indonesian document numbering.
* Complete Indonesian (id_ID) translation.
