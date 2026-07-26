# Plan 03 — Payments and Kwitansi

**Status:** Planned · **Target:** 0.6.0 · **Table exists:** `bizwit_payments`

## Goal

A user can record money that has already arrived, see what an invoice still owes,
and issue a kwitansi the client can sign.

**Scope boundary, restated because this screen is where it gets tested:** BizWit
records payments. It never initiates, processes, or holds money. No gateway, no
card handling, no bank API that moves funds.

## Why it is not trivial

**Partial payments are the normal case.** Indonesian B2B pays in termin, so an
invoice usually has several payments against it. `paid_minor` on the invoice is a
cache of the sum of its payments, and every write path — create, edit, delete,
void — has to keep it true. Recomputing from the payments table on write is safer
than incrementing.

**Withholding makes the bank credit smaller than the invoice.** A client
withholding PPh 23 pays the net and remits the rest. Recorded naively, the
invoice looks permanently underpaid. The payment record needs to distinguish
*received in bank* from *withheld and remitted on your behalf*, and both count
toward settling the invoice.

**Overpayment and rounding happen.** Someone transfers a round number, or a bank
fee is deducted from the transfer. The screen needs a sane answer for
"payment ≠ balance" that is not a validation error the user cannot get past.

**Kwitansi have legal form.** Terbilang, and meterai above the threshold — see
[`../docs/indonesia.md`](../docs/indonesia.md#bea-meterai). The document must
leave room to physically affix meterai and be signed.

**Receipt numbering is a separate series** from invoices, allocated from its own
`Sequence` key.

## Scope

- Payments list with client, invoice, method and date filters.
- Record-payment form, reachable from an invoice or standalone.
- Methods from the region: transfer, tunai, QRIS, e-wallet, virtual account,
  giro, kartu, SP2D, kompensasi.
- Withholding field on a payment, counting toward settlement.
- Invoice status updated on payment: partial or paid.
- Printable kwitansi with terbilang and a meterai reminder.
- Delete a payment and correctly restore the invoice status.

## Out of scope

- Any payment gateway or bank API that moves money.
- Automatic bank statement reconciliation. Importing a statement to *match*
  against records is a candidate for [05-import-export.md](05-import-export.md).
- Refunds as a first-class concept. A negative payment covers it for now.
- Multi-invoice payment allocation in one form — one payment, one invoice, for
  the first version.

## Tasks

- [ ] `Payment_Repository`
- [ ] Recompute-from-source helper keeping `invoices.paid_minor` true
- [ ] Payment form with region payment methods
- [ ] Withholding field, distinguishing received from withheld
- [ ] Invoice status transition on payment create / edit / delete
- [ ] Overpayment handling: warn, allow, and show the credit
- [ ] Receipt numbering from its own sequence key
- [ ] Printable kwitansi with terbilang
- [ ] Meterai reminder above the threshold, when enabled in settings
- [ ] Payments visible on the invoice and client screens
- [ ] Indonesian translation for every new string
- [ ] Tests: balance after partial/full/over payment, delete restores status,
      withholding counts toward settlement, terbilang on the document

## Acceptance criteria

- Recording a partial payment moves the invoice to `partial` and shows the
  remaining balance.
- An invoice paid net of PPh 23 can reach `paid` without a fake extra payment.
- Deleting a payment returns the invoice to its correct prior status.
- A kwitansi over Rp 5.000.000 shows the meterai reminder.
- Every kwitansi shows the amount in terbilang.
- No code path in this feature can initiate a transfer.

## Risks and open questions

- **Does withholding belong on the payment or the invoice?** It is agreed at
  invoice time but evidenced at payment time (bukti potong). Provisionally
  recorded on the payment, referencing the invoice.
- **Bukti potong reference.** Worth a field so the user can find the withholding
  certificate later. Likely yes.
- **Should a single payment settle several invoices?** Common with a monthly
  bulk transfer. Deferred to a later version; note it so the schema does not
  make it impossible.
