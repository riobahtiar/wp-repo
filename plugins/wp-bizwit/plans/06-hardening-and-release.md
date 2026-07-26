# Plan 06 — Hardening and 1.0 Release

**Status:** In progress (0.9.0 RC shipped: activity + stats cache) · **Target:** 0.9.0 – 1.0.0

## Goal

Leave beta honestly: the plugin holds someone's financial records, and 1.0 is a
statement that it can be trusted to.

## Why it is not trivial

**Test coverage is currently thin where it matters most.** `MoneyTest` and
`IndonesiaRegionTest` cover the pure logic. The repository layer — sanitisation,
the delete guard, the meta whitelist — has no tests at all, and that is the code
standing between `$_POST` and the database.

**There is no audit trail.** Nobody can answer "who changed this invoice and
when". For a plugin holding tax-relevant records that is a real gap, and adding
it later means the history starts empty.

**Performance has never been measured.** Dashboard aggregates are unbounded
queries. Fine with 20 clients; unknown with 20,000 invoices.

**Security review has to be done by someone other than the author.** Self-review
finds what the author was already thinking about.

## Scope

### Testing
- Repository tests: sanitisation whitelist, validation, delete guards, meta.
- Screen tests: capability enforcement, nonce failure, post/redirect/get.
- Concurrency test for `Sequence` under parallel allocation.
- A fixture set of realistic Indonesian data — mononyms, long titles, nine-digit
  rupiah, all four client types.

### Audit trail
- `bizwit_activity` table: actor, entity, action, timestamp, changed fields.
- Written by repositories, not screens.
- Retention policy — it grows without bound otherwise.
- Surfaces as recent activity on the dashboard.

### Performance
- Benchmark with 10k clients and 50k invoices.
- Index review against the queries actually issued.
- Cache dashboard aggregates with sensible invalidation.
- Confirm list-table pagination does not degenerate to a full scan.

### Security
- Full pass against [`../SECURITY.md`](../SECURITY.md).
- External review, or at minimum a second pair of eyes.
- Verify every state change has both nonce and capability.
- Verify every output is escaped at the point of output.
- Verify every query is prepared, and that no table or column name is
  interpolated from input.
- Check capability enforcement as each role, not just as administrator.

### Release
- Accessibility audit (WCAG 2.1 AA).
- Browser and device testing.
- Native-speaker review of every Indonesian string.
- Upgrade test: install 0.3.0, add data, upgrade to 1.0.0, verify migration.
- Downgrade note — what breaks if someone rolls back.

## Tasks

- [ ] Repository test suite
- [ ] Screen capability and nonce tests
- [ ] Sequence concurrency test
- [ ] Indonesian fixture dataset
- [x] `bizwit_activity` table and repository writes
- [x] Activity retention and pruning
- [ ] Benchmark at 10k / 50k rows
- [x] Index review (status_due on invoices)
- [x] Dashboard aggregate caching
- [ ] Security self-review against SECURITY.md
- [ ] External security review
- [ ] Accessibility audit and fixes
- [ ] Native-speaker translation review
- [ ] Upgrade path test from 0.3.0
- [ ] Freeze the schema; document the stability promise
- [ ] Tag 1.0.0, then unblock [05-import-export.md](05-import-export.md)

## Acceptance criteria

- Repository and screen tests cover every write path.
- Every invoice change is attributable to a user and a time.
- Dashboard loads in under 500ms with 50k invoices.
- Security review completed by someone other than the author, findings closed.
- Upgrade from 0.3.0 with real data loses nothing.
- Every Indonesian string reviewed by a native speaker.

## Risks and open questions

- **Audit trail growth.** A busy install generates a lot of rows. Needs a
  retention default that does not quietly destroy records someone relies on.
- **Schema freeze is a commitment.** After 1.0, every change needs a migration
  that runs against real data. Make sure the model is right first — that is what
  the beta is for.
- **Who does the security review?** Needs deciding, and possibly budgeting.
