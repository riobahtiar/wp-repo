# WP BizWit Documentation

Back-office record keeping inside wp-admin — clients, projects, invoices and
payment receipts — **built for Indonesian companies and UMKM first**.

## Start here

| Document | What it covers |
|----------|----------------|
| [indonesia.md](indonesia.md) | The Indonesian profile: NPWP, NIB, PKP, PPN, PPh 23, bea meterai, terbilang, document numbering, and the glossary |
| [culture.md](culture.md) | Designing for Indonesian users: names, tone, WhatsApp, documents, the Lebaran calendar, payment behaviour |
| [data-model.md](data-model.md) | The seven database tables, why custom tables, and the rules the schema depends on |
| [development.md](development.md) | Build, lint, test, translation workflow, and the tooling traps in this project |

## The two things that shape every decision here

### 1. Indonesia is the default, not a setting

The users are Indonesian: PT, PT Perorangan, CV, UD and koperasi, most of them
UMKM, invoicing other Indonesian companies and government instansi. A fresh
install is already in the Indonesian profile with rupiah as the currency —
nobody should have to find a setting to get correct paperwork.

Businesses elsewhere are supported through the same abstraction (set
**BizWit → Settings → Business region**), but when a design decision has to
favour one audience, it favours Indonesia.

This goes beyond tax rules into how the software should behave for the people
using it — single-word names, formal `Anda` copy, WhatsApp as the real delivery
channel, documents that leave room for a signature and company stamp, and a
calendar where Lebaran moves everyone's cashflow. See [culture.md](culture.md).

### 2. Record keeping only — no payment processing

BizWit never processes, moves, or holds money. There is no gateway integration,
no card handling, no payment initiation. "Payments" means recording a payment
that already happened elsewhere — a bank transfer, cash, QRIS — and issuing the
matching kwitansi.

This is a scope boundary, not a missing feature. A request to add payment
processing is a change of product, and should be discussed as one.

## A distinction worth getting right up front

**Region is not language.** They are two independent axes:

| | Set by | Controls |
|---|---|---|
| **Interface language** | WordPress site locale + `languages/*.mo` | The words wp-admin is written in |
| **Regional profile** | `Localization\Regions`, from BizWit settings | Business vocabulary, which fields exist, tax rules, number and date formats, document numbering |

An Indonesian company running wp-admin in English still needs a field labelled
NPWP, a Provinsi dropdown, and kwitansi carrying the amount in words. Tying
domain vocabulary to `get_locale()` would force a language switch to get correct
paperwork, so it is never done.

## Current state

**Built** — all seven tables with versioned migrations, capabilities and roles,
admin menu, dashboard, full Clients CRUD, settings, the regional profile layer,
and a complete Indonesian translation.

**Placeholder** — Projects, Invoices and Payments screens show an honest "not
built yet" panel. Their tables and the Indonesian rules that govern them already
exist, so no migration is needed when the interfaces land.
