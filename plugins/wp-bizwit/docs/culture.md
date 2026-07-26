# Building for Indonesian Users

Practical conventions for software used by Indonesian businesses and the people
inside them. This is about product decisions — what a form accepts, how copy is
worded, what a document has to carry — not etiquette advice.

> Indonesia is plural: 38 provinces, six recognised religions, hundreds of
> languages, and business culture that differs between a Jakarta corporate, a
> Surabaya trading family and a Bali tourism operator. Treat everything here as
> the **common default to design for**, never as an assumption about any
> individual user. Where a convention varies, make the field flexible rather than
> encoding one answer.

---

## Names

**Many Indonesians have a single legal name.** Sukarno, Suharto, Prabowo — one
word, no surname, and that is what appears on their KTP.

- **Never require a surname.** A mandatory "last name" field locks out a large
  share of the population and forces people to type a placeholder.
- Use **one full-name field**. If you must split names, make the second part
  optional and never validate for its presence.
- BizWit stores `display_name` and `legal_name` as single fields for exactly
  this reason.

**Academic and honorific titles are part of a formal name** and belong on
documents: `Ir.`, `Dr.`, `H.` / `Hj.` (Haji / Hajjah) before the name; `S.E.`,
`S.T.`, `S.H.`, `M.M.`, `Ak.`, `CPA` after it. On a contract or kwitansi the
signatory is written with them. Do not strip punctuation from name fields or
cap them so short that `Drs. H. Bambang Suryanto, S.E., M.M.` will not fit.

**Address people as `Bapak` / `Ibu`** (abbreviated `Bpk.` / `Ibu`) in formal
correspondence, not by bare first name. `Yth.` (*Yang terhormat*) opens a formal
letter.

## Interface language and tone

**Use `Anda`, never `kamu`.** Business software addresses the user formally. The
informal second person reads as a consumer app talking down to a professional.

Indonesian UI copy is politer than English UI copy, and the politeness markers
are not optional decoration:

| Instead of | Write |
|---|---|
| "Enter your NPWP" | "Masukkan NPWP Anda" |
| "Invalid input" | "Format yang dimasukkan belum sesuai" |
| "Delete this?" | "Yakin ingin menghapus data ini?" |
| Bare imperative | Prefix with **Silakan** (please) or **Mohon** (kindly) where a request is being made |

Destructive confirmations should say what is lost, not just ask. Error messages
should say what to do next, not only what failed.

**Domain terms stay in Indonesian, even in an English interface.** Faktur,
kwitansi, NPWP, PPN, termin, meterai, terbilang are the names of real documents
and obligations — translating them into English invents terms nobody uses. This
is why BizWit separates the regional profile from the site locale.

## Communication channels

**WhatsApp is the default business channel in Indonesia**, ahead of email for a
great deal of B2B contact — invoices are commonly sent as a PDF over WhatsApp,
and payment confirmations arrive as a photo of a transfer receipt.

Practical consequences:

- **Phone numbers are as important as email addresses.** Do not treat phone as
  optional metadata.
- Store numbers in a format that survives being pasted into WhatsApp. Indonesian
  mobiles are written `08xx-xxxx-xxxx` domestically and `+62 8xx-xxxx-xxxx`
  internationally — accept both and do not strip the leading zero or the `+`.
- Any "send document" feature should assume a downloadable PDF that a human
  forwards, not only an SMTP delivery.

## Documents

An Indonesian business document is expected to carry more than the numbers:

- **Kop surat** — letterhead with the business name, full address, phone and
  NPWP.
- **Nomor surat** — a composite document number, not a bare counter. See
  [indonesia.md](indonesia.md#document-numbering).
- **Tanda tangan dan cap** — a signature *and* the company rubber stamp
  (`cap` / `stempel perusahaan`). Even where not strictly required by law, a
  document without a stamp is routinely sent back. Leave space for both on any
  printable output.
- **Meterai** — physically affixed or e-meterai on documents stating amounts
  above the threshold. See [indonesia.md](indonesia.md#bea-meterai).
- **Terbilang** — the amount in words on a kwitansi.
- **Rekening tujuan** — bank name, account number and account holder. An invoice
  without transfer details is incomplete, because that is how it will be paid.

Printable output should therefore be designed for **A4**, not US Letter, and
should survive being printed in black and white and photographed.

## The business calendar

Cashflow and collections follow the religious and national calendar closely.
Anything that schedules, forecasts, or chases payment needs to know this.

**Idulfitri (Lebaran)** is the single biggest event in the Indonesian business
year:

- **THR** (*Tunjangan Hari Raya*), a religious holiday allowance, is legally
  required and must be paid before the holiday — a large, predictable cash
  outflow every employer plans around.
- **Cuti bersama**, government-declared collective leave, extends the holiday
  into one to two weeks where much of the country genuinely stops. Many people
  travel home (*mudik*).
- Expect invoices issued shortly before Lebaran to be paid late, and expect a
  push to collect receivables *before* it.

Also worth designing around:

- **Ramadan** — shifted working hours at many employers; meetings cluster in the
  morning.
- **Nyepi** in Bali — a complete 24-hour shutdown, including the airport.
- **Christmas, Waisak, Nyepi, Imlek and Idul Adha** are all public holidays;
  Indonesia recognises multiple faiths and the national holiday calendar
  reflects that.
- **Friday prayers**, roughly 11:30–13:30, keep Friday midday clear.
- The **fiscal year is the calendar year** for most businesses, so annual
  reporting clusters in the first quarter.

Do not hardcode a holiday list that will silently rot. If a feature needs
business days, make the calendar editable.

## Money on screen

- Written `Rp 1.500.000` — full stop for thousands, comma for decimals, and in
  practice no decimals at all. `1,500,000` reads as one and a half.
- Amounts are large. A modest project invoice runs to eight or nine digits, so
  column widths, input fields and chart axes must not assume four-figure
  numbers.
- Prices are commonly discussed in **juta** (million) and **miliar** (billion).
  A summary figure of "Rp 1,5 juta" is more readable than the full number.

## Payment behaviour

- **Bank transfer dominates**, with QRIS and e-wallets common for smaller
  amounts and virtual accounts for anything automated.
- **Payment terms are negotiated per client** (*tempo*), commonly 14 or 30 days
  but frequently longer with large corporates and government.
- **Staged payment is the norm on projects**: uang muka (down payment), termin
  (installments tied to progress), and retensi (retention held back until after
  handover, common in construction).
- **BAST** (*Berita Acara Serah Terima*, handover minutes) usually has to be
  signed before an invoice can even be submitted. A billing model that assumes
  "deliver, then invoice" without an acceptance step will not match reality.
- **Government clients pay through SP2D** via KPPN, on their own timetable.
  Their process is document-heavy and slow; treat long outstanding periods as
  normal rather than as an error state.

## Addresses

Indonesian addresses are more granular than the Western default and are written
largest-last:

```
Jl. Gatot Subroto No. 45
RT 003 / RW 005
Kelurahan Kuningan Barat
Kecamatan Mampang Prapatan
Jakarta Selatan
DKI Jakarta 12930
```

`RT` / `RW` are neighbourhood units below the kelurahan and appear on official
paperwork. A form with only "Address line 1 / City / State" cannot hold a real
Indonesian address. See [indonesia.md](indonesia.md#address).

## Tax rhythm

Filing is monthly and annual, and the deadlines drive when clients want their
records to be right. Corporate annual returns are due some months after the
fiscal year ends, with monthly obligations in between, and the rules move —
**do not hardcode deadlines**. Surface the data a bookkeeper needs and let them
work to whatever the current schedule is.

The practical software consequence: **records must be exportable and complete
per period**, because someone will have to reconcile them against a filing.

---

## Checklist for any new feature

- [ ] Does every name field accept a single-word name?
- [ ] Do name fields allow titles with full stops and commas, and are they long enough?
- [ ] Is the copy in `Anda`, with `Silakan` / `Mohon` where a request is made?
- [ ] Are domain terms Indonesian (faktur, kwitansi, NPWP), not translated?
- [ ] Is the phone number field prominent, and does it accept `08xx` and `+62`?
- [ ] Do printable documents leave room for signature, cap and meterai?
- [ ] Do they show the bank account details?
- [ ] Is money formatted `Rp 1.500.000`, with room for nine digits?
- [ ] Does anything date-driven degrade gracefully across a two-week Lebaran gap?
- [ ] Can the address hold RT/RW, kelurahan and kecamatan?
- [ ] Is the Indonesian translation shipped, not just the `__()` wrapper?
