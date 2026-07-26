# Plan 05 — Import and Export

**Status:** 🔒 Deferred · **Gate:** not before 1.0 GA

> **Do not start this while BizWit is in beta.** This is a deliberate hold, not a
> backlog position.

## Why it is gated

**An importer writes data that outlives the beta.** While the schema can still
change, an import can fill a database with thousands of rows shaped for a model
that is about to be revised. Those rows then need a migration written for
malformed data nobody reviewed — the worst kind of migration.

**Import is the highest-risk surface in the plugin.** It takes an
attacker-controllable file and turns it into database rows. Every input rule the
UI enforces has to be re-enforced, plus a set the UI never faces: CSV formula
injection, zip bombs, memory exhaustion on large files, and encoding confusion.
Building it while the validation rules are still moving means building it twice
and reviewing it once.

**Export is safer, but the two ship together.** An export whose format changes
after people have scripted against it is a broken promise. Once we publish a
format we support it.

**The gate is checkable.** Start when: all six admin screens are built, the
schema has been stable for one release, and [06-hardening-and-release.md](06-hardening-and-release.md)
has been completed.

## Goal, when unblocked

A user can get their data out in a format they can open, hand to an accountant,
or move to another install — and can bring existing client and invoice records in
from a spreadsheet without retyping them.

## Scope, when unblocked

**Export**
- CSV per entity: clients, projects, invoices, invoice items, payments.
- A full JSON backup of every BizWit table, versioned by schema version.
- Filtered exports: date range, client, status. An accountant wants one period,
  not everything.
- Excel-friendly CSV: UTF-8 BOM so Indonesian names and `Rp` do not mangle in
  Excel, and a documented delimiter, since a locale using comma as decimal
  separator makes comma-delimited CSV ambiguous.

**Import**
- Clients first — the simplest entity and the most commonly retyped.
- Column mapping UI. Never assume header names.
- Dry-run preview showing what would be created, updated and skipped, before
  anything is written.
- Duplicate detection on NPWP, email, then name.
- Per-row error report; a bad row must not abort the run.
- Chunked processing so a large file cannot exhaust memory or time out.
- Rollback, or at minimum a labelled import batch that can be undone.

## Out of scope

- Live sync with accounting software.
- e-Faktur / Coretax file formats — regulated, and a separate plan.
- Importing from a specific competitor's format, until someone asks.

## Security requirements

Non-negotiable. See [`../SECURITY.md`](../SECURITY.md).

- Capability check plus nonce on both operations. Export leaks the entire client
  book; treat it as privileged.
- Never trust the uploaded MIME type; validate content.
- **Neutralise CSV formula injection on export.** A cell beginning `=`, `+`, `-`
  or `@` executes when opened in Excel. Prefix with `'`. This is an attack on the
  user's accountant, launched through our export.
- Enforce a maximum file size and row count.
- Write uploads outside the web root, or delete immediately after processing.
- Import must go through the same repository sanitisation as the UI — no direct
  `$wpdb` writes from the importer.
- Rate-limit or lock so two concurrent imports cannot interleave.
- Log who imported what and when.

## Tasks, when unblocked

- [ ] Confirm the gate: all screens built, schema stable one release, hardening done
- [ ] Decide and document the CSV dialect (delimiter, encoding, BOM, date format)
- [ ] Exporter with formula-injection neutralisation
- [ ] Filtered export UI
- [ ] JSON backup including schema version
- [ ] Chunked upload and parse with progress
- [ ] Column mapping UI
- [ ] Dry-run preview
- [ ] Duplicate detection and merge strategy
- [ ] Per-row error report, downloadable
- [ ] Import batch labelling and undo
- [ ] Indonesian translation for every new string
- [ ] Tests: malformed CSV, formula injection, oversized file, duplicate handling,
      partial failure, encoding edge cases with Indonesian names

## Acceptance criteria

- Exported CSV opens in Excel with Indonesian names and rupiah intact.
- A cell starting with `=` is neutralised on export.
- A 10,000-row import completes without exhausting memory.
- A file with 50 bad rows imports the good ones and reports the rest.
- Dry-run shows exactly what will happen, and nothing is written until confirmed.
- An import can be undone.
- Import cannot bypass repository validation.

## Open questions

- **Excel `.xlsx` as well as CSV?** Needs a library, which needs Strauss
  prefixing. CSV first.
- **Should export include internal notes?** They are marked "never shown to the
  client", and an export handed to an accountant is a disclosure. Probably
  opt-in.
- **Undo depth.** Full rollback needs either a transaction across a large import
  or a recorded batch. Batch labelling is simpler and probably enough.
