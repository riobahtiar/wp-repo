# Plan 01 — Projects

**Status:** Done · **Target:** 0.4.0 · **Shipped:** `bizwit_projects` + `bizwit_project_terms`

## Goal

A user can record the work they are doing for a client, on the terms they agreed
to bill it under, and later create an invoice from it without retyping anything.

## Why it is not trivial

**Billing types are not interchangeable.** Fixed price, hourly, per-termin and
retainer produce genuinely different invoices, and the project screen has to
capture enough for each without showing all four sets of fields to everyone.
Progressive disclosure is not optional here.

**Termin is the Indonesian default and the awkward case.** A project billed in
termin needs an ordered list of stages with amounts or percentages, each
invoiced separately, each usually gated on a signed BAST. That is a child table,
not a field. Modelling termin as "just milestones" and discovering later that
they must sum to the contract value, support retensi, and survive re-ordering is
the likely rework.

**Budget tracking crosses a table that does not exist yet.** "How much of this
project has been invoiced" needs invoices. Until 0.5.0 the figure is either
absent or a stub, and a stub that silently reads zero looks like a bug.

**A freelancer may not want projects at all.** Many users invoice a client
directly with no project in between. Projects must be optional everywhere,
including on the invoice screen.

## UI approach

Follow [`../docs/frontend-architecture.md`](../docs/frontend-architecture.md).
**v1 shipped as `WP_List_Table` + PHP form** (no Vue required). A richer termin
builder island can land later without changing the schema — stages already live
in `bizwit_project_terms`.

## Scope

- Projects list: `WP_List_Table` with search, client filter, status filter.
- Add/edit form following the clients-form disclosure pattern.
- Fields: client, name, code, status, billing type, currency, rate, budget,
  start/end dates, description.
- Termin as an ordered child list, stored in a new `bizwit_project_terms` table.
- Delete guard: refuse when invoices reference the project.
- Client detail view showing that client's projects.

## Out of scope

- Time tracking. Hourly billing records a rate; entering hours belongs to
  invoicing or a later plan.
- Task or to-do management. This is not a project management tool.
- Gantt charts, resourcing, team assignment.
- File attachments (contracts, SPK, BAST scans) — separate plan if wanted.
- Drag-and-drop termin reorder / Vue island (optional later).

## Tasks

- [x] Add `bizwit_project_terms` to `Schema`, bump `Installer::DB_VERSION`
- [x] `Project_Repository` extending `Repository`, with sanitise + validate
- [x] Delete guard against invoices, mirroring `Client_Repository::delete()`
- [x] `Projects_Table` list table
- [x] Replace `Projects_Screen` placeholder with list/add/edit routing
- [x] Termin sub-form: add rows (blank rows + replace-all on save)
- [x] Validation: termin amounts sum to the project value, or an explicit override
- [x] Region labels — proyek, termin, retensi, SPK, nomor kontrak
- [x] Show projects on the client edit screen
- [x] Indonesian translation for every new string (catalogue; compile at release)
- [x] Tests: repository sanitisation, delete guard, termin sum validation

## Acceptance criteria

- A freelancer can create a project with a name and a client and nothing else.
- A contractor can create a termin-billed project whose stages sum to the
  contract value, and is warned when they do not.
- Deleting a project with invoices is refused with an explanation naming the
  count.
- Every new label is region-aware and translated.
- The projects list is usable with 500 projects.

## Risks and open questions

- **Does retensi belong to the project or the invoice?** It is agreed at
  contract level but applied at payment. Provisionally: percentage on the
  project, applied when invoicing. Needs a real contractor's opinion.
- **Should a project be able to span multiple clients?** Assumed no.
- **Currency on project vs client.** If they differ, the invoice must pick one.
  Provisionally the project wins, defaulting from the client.
