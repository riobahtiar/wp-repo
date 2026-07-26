# Plan 02 — Invoices

**Status:** Done · **Target:** 0.5.0 · **Shipped:** `bizwit_invoices` + items + print + overdue cron

## Goal

A user can issue an invoice a client will actually accept — correct numbering,
correct tax treatment for their status, and a printable document carrying
everything Indonesian paperwork expects.

This is the most consequential screen in the plugin. A wrong invoice is a
compliance problem for the user, not a bug report.

## UI approach

Requires the [frontend foundation](07-frontend-modernization.md). The **line-item
editor** is a Vue screen (dynamic rows, tax preview, money inputs). List can be
`WP_List_Table`. **Printable faktur** stays server-rendered HTML/PDF — Vue does
not own document output. Money in JSON is always integer minor units. Stack and
budgets: [`../docs/frontend-architecture.md`](../docs/frontend-architecture.md).

## Why it is not trivial

**The arithmetic has real traps.**

- Per-line tax, per-line discount, and a header-level discount interact. Decide
  the order once and document it, because line-then-header and header-then-line
  give different totals.
- Rounding must happen once, at the boundary, in `Support\Money`. Rounding each
  line and then summing gives a different answer from summing then rounding, and
  clients will notice the one-rupiah difference.
- `quantity` is `DECIMAL(14,4)`. `quantity × unit_price_minor` must not be
  computed in floating point.

**Tax treatment is driven by status, not by a field.** Only a PKP may charge PPN
(`Settings::charges_sales_tax()`). The invoice form must not offer a PPN line to
anyone else, and must not silently apply a stored rate. A non-PKP invoice
carrying PPN is billing tax the business has no right to collect.

**PPh 23 changes what arrives in the bank.** A corporate client withholds it and
remits it on the seller's behalf, so the invoice total and the bank credit differ
by design. The invoice needs to state the withheld amount and the net expected,
or reconciliation in the payments screen will never work.

**Immutability.** Once an invoice is sent, editing it silently is wrong. Needs a
decided rule — provisionally: freely editable while `draft`, and after that only
via an explicit revision or a void-and-reissue.

**Numbering has a gap problem.** `Sequence::next()` consumes a number on
allocation, so a failed save leaves a gap. Acceptable for internal numbering;
possibly not for a series presented to a tax authority. Options: allocate on
successful commit only, or record voided numbers explicitly so the series is
auditable. **Decide before shipping, not after numbers exist.**

**Printing is a real feature, not a stylesheet.** See the document expectations
in [`../docs/culture.md`](../docs/culture.md#documents): kop surat, nomor,
signature and cap space, meterai where required, bank details, A4.

## Scope

- Invoice list with status filters and an overdue view.
- Invoice editor: client, optional project, dates, line items, discounts, tax,
  notes and terms.
- Line item repeater with add, remove, reorder.
- Status transitions: draft → sent → partial → paid, plus overdue and void.
- Totals recomputed server-side on save. Never trust a posted total.
- Printable HTML document, print-stylesheet based.
- Create-from-project, including termin.

## Out of scope

- Emailing invoices. Users forward PDFs over WhatsApp; a send feature is a
  separate plan.
- PDF generation via a PHP library. Browser print-to-PDF first; revisit if users
  ask.
- e-Faktur / Coretax integration. Large, regulated, and needs real DJP
  credentials. Separate plan, post-1.0 at the earliest.
- Recurring invoices.
- Multi-currency conversion.

## Tasks

- [x] `Invoice_Repository` (items nested; no separate public item repo needed)
- [x] A `Totals` calculator with the ordering rules documented and unit-tested
- [x] Line item repeater UI (PHP rows in v1; Vue editor deferred)
- [x] Tax section rendered only when `Settings::charges_sales_tax()`
- [x] PPh 23 withholding line, showing gross, withheld and net expected
- [x] Status machine with allowed transitions in one place (`Invoice_Status`)
- [x] Void flow that preserves the number and the record
- [x] Numbering decision: provisional draft numbers; permanent on leave-draft; gaps accepted for v1
- [x] Printable template with region document notes (HTML/print CSS, A4)
- [x] Create-from-project (query args `project_id` / `term_id`)
- [x] Overdue detection — daily cron `wp_bizwit_mark_overdue_invoices`
- [x] Indonesian translation for every new string
- [x] Tests: totals arithmetic, tax gating, transitions (concurrency left for later)
- [ ] Vue line-item editor island (optional follow-up)
- [ ] Concurrent numbering stress test

## Acceptance criteria

- A freelancer with tax off can issue an invoice with no tax field anywhere on
  the form or the printed document.
- A PKP invoice shows PPN correctly; a non-PKP invoice cannot show it at all.
- An invoice with PPh 23 states gross, withheld and net expected.
- Totals recompute server-side; a tampered posted total is ignored.
- Two concurrent saves never produce the same invoice number.
- The printed invoice fits A4 with room for signature, cap and meterai.

## Risks and open questions

- **Gap-free numbering.** Needs a decision with the business owner. Genuinely
  gap-free requires allocating at commit and handling failure, which is more
  complex than the current design.
- **Discount ordering.** Line-level then header-level is assumed. Confirm against
  how the user's clients actually read an invoice.
- **Editing a sent invoice.** Revision history or void-and-reissue? Revisions
  need another table.
- **Rounding rule for IDR.** Whole rupiah, so per-line rounding is visible.
  Confirm round-half-up is what a bookkeeper expects.
