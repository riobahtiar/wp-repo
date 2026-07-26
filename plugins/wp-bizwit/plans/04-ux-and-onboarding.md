# Plan 04 — UX and Onboarding

**Status:** Partly done · **Target:** 0.7.0

## Goal

Someone installs BizWit and issues their first invoice without reading anything,
whether they are a freelance designer with no tax obligation or a PT with a
bookkeeper.

## Already shipped

- **Tax is off by default.** A user with no tax obligation never sees a tax
  field. `Settings::handles_tax()` gates them everywhere.
- **Business type** — individual vs registered entity — controls which fields are
  shown by default without ever making one unreachable.
- **Progressive disclosure** on the client form and settings, built on native
  `<details>` so it works with keyboard, screen reader, find-in-page and print.
  Collapsed summaries show current state, so a closed section is informative
  rather than merely hidden.
- **A client needs only a name.** Everything else is optional.
- **Phone is a first-class field**, linked to WhatsApp in the clients list.

## Why the rest is not trivial

**An empty install is the worst-looking state and the one every user sees
first.** Empty tables and zeroed tiles read as broken. Empty states need to
explain and offer the next action.

**A wizard is easy to get wrong.** A modal that blocks the admin, cannot be
skipped, or reappears is worse than no wizard. It must be dismissible, resumable
and never block.

**"Simple by default" fights "discoverable".** A user who does need NPWP must be
able to find it without being told a section exists. Summaries carrying current
state is the current answer; it needs testing on real users.

**No JavaScript is a deliberate constraint so far.** Line item repeaters in
[02-invoices.md](02-invoices.md) will need JS. Where it appears, it must enhance
a form that already works without it.

## Scope

- Empty states for every list, with a primary action and one line of context.
- Dismissible setup checklist on the dashboard: business details, bank account,
  first client. Disappears when complete.
- Dashboard: ageing summary, recent activity, quick actions.
- Inline validation feedback on forms rather than only a top notice.
- Accessibility pass: focus order, labels, colour contrast, error announcement.
- Mobile-usable admin screens — list tables are the weak point.
- Print stylesheet shared by invoice and kwitansi.

## Out of scope

- A React or block-editor rewrite of the admin.
- A custom design system. Stay within wp-admin conventions; users already know
  them.
- Dark mode beyond what wp-admin gives.

## Tasks

- [ ] Empty state component used by every list table
- [ ] Dismissible setup checklist, stored per user
- [ ] Dashboard ageing summary (needs invoices)
- [ ] Recent activity feed (needs an audit trail — see PROGRESS gaps)
- [ ] Inline field-level errors, keeping submitted values on failure
- [ ] Accessibility audit against WCAG 2.1 AA, then fixes
- [ ] Responsive review of list tables at mobile widths
- [ ] Shared print stylesheet
- [ ] Confirm-before-leaving on dirty forms (progressive enhancement)
- [ ] Indonesian translation for every new string

## Acceptance criteria

- A new install shows a checklist, not an empty dashboard of zeroes.
- Every list table has a useful empty state with a primary action.
- A validation failure keeps what the user typed and points at the field.
- Keyboard-only navigation reaches every control, in a sensible order.
- Every form works with JavaScript disabled, or degrades explicitly.
- Admin screens are usable at 375px wide.

## Risks and open questions

- **Does the checklist belong on the dashboard or as an admin notice?** Notices
  are noisy and easy to dismiss forever. Dashboard is assumed.
- **How much JS is acceptable?** The current no-JS baseline is a real
  accessibility and reliability win; give it up only where the interaction
  genuinely needs it.
- **Copy tone.** Indonesian business UI is politer than English UI — see
  [`../docs/culture.md`](../docs/culture.md#interface-language-and-tone). Worth
  a native speaker reviewing every string before 1.0.
