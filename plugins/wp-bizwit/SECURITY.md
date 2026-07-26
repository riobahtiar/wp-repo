# Security Policy — WP BizWit

BizWit holds a business's client list, invoices and payment records. A breach
here exposes commercially sensitive data and personal data covered by
Indonesia's **UU PDP (UU 27/2022)**. Security is not a polish item.

**Status: beta (0.3.0).** No external security review has been done yet. That is
tracked in [plans/06-hardening-and-release.md](plans/06-hardening-and-release.md)
and gates 1.0.

---

## Reporting a vulnerability

**Do not open a public issue.** Report privately to the plugin maintainer, and
include:

- What you found and where (file and line if you have it).
- Steps to reproduce, or a proof of concept.
- What an attacker could do with it.
- Which version you tested.

You will get an acknowledgement, an assessment, and credit in the changelog
unless you would rather not be named. Please give a reasonable window to fix
before disclosing publicly.

## Supported versions

| Version | Supported |
|---------|-----------|
| 0.3.x | ✅ Current beta |
| < 0.3 | ❌ Upgrade |

Pre-1.0 means fixes land on the latest version only. There are no backports.

---

## What this plugin deliberately does not do

Some of the strongest security properties come from absence:

- **No payment processing.** No card data, no gateway credentials, no ability to
  move money. There is nothing here worth attacking for financial gain directly.
- **No secrets stored.** No API keys, no bank credentials, no OAuth tokens.
- **No public (anonymous) data endpoints.** Admin UI and the `wp-bizwit/v1` REST
  API both require a logged-in user with a BizWit capability. Cookie auth plus
  `X-WP-Nonce` (`wp_rest`) for REST; no shortcodes rendering client data on the
  front end.
- **No file uploads.** Nothing accepts a file. (This changes when
  [import/export](plans/05-import-export.md) lands, which is why that plan has a
  security section of its own and is gated on a hardening pass.)
- **No third-party runtime dependencies.** Composer dev dependencies only; no
  vendored library executes in production. When one is added, Strauss prefixes
  it so two plugins cannot collide on incompatible versions.

## Threat model

| Threat | Mitigation |
|--------|-----------|
| SQL injection | Every query is built in a repository using `$wpdb->prepare()`. Table and column names cannot be placeholders, so they are resolved against hardcoded whitelists — never interpolated from input. See `Client_Repository::safe_orderby()`. |
| Stored XSS via client data | Output escaped at the point of output (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`) regardless of what sanitisation ran on the way in. |
| CSRF | Every state change verifies a nonce with `check_admin_referer()` as the first statement of its handler. Destructive actions are POST; the one GET delete link carries a per-record nonce. |
| Privilege escalation | Six purpose-built capabilities. `Screen::render_page()` re-checks the capability on every request — registering a menu page with a capability only hides the menu item, the URL stays reachable. |
| Mass assignment | Repositories build the column list from a hardcoded array. An unexpected `$_POST` key can never reach a query. Region meta fields are filtered against the active region's declared list. |
| Data loss | Deactivation never touches data. Uninstall only drops tables when explicitly opted in. Deleting a client with dependent records is refused. |
| Open redirect | Redirects use `wp_safe_redirect()`, which restricts to the site host. |
| Race conditions | Document numbers are allocated in a single atomic statement, not read-modify-write. |

## Rules for contributors

Every one of these is a boundary, not a style preference.

### Database

- **All `$wpdb` access goes through a repository.** This keeps the `phpcs:ignore`
  suppressions that direct queries require confined to a few audited methods,
  instead of scattered across screens where a missing `prepare()` is easy to miss
  in review.
- **`$wpdb->prepare()` with `%s` / `%d` placeholders, always.**
- **Table and column names are never taken from input.** Whitelist them. An
  `ORDER BY` column from `$_GET` is an injection point.
- **Pass a format array** to `$wpdb->insert()` / `->update()`. Without it,
  `$wpdb` guesses `%s` for everything.

### Input

- `wp_unslash()` before sanitising — WordPress slashes all superglobals.
- Sanitise with the right function for the field: `sanitize_text_field()`,
  `sanitize_email()`, `esc_url_raw()`, `sanitize_textarea_field()`,
  `absint()`, `sanitize_key()`.
- Validate against a whitelist wherever the value is an enum.
- **Never trust a posted total.** Recompute money server-side.

### Output

- Escape at output, every time, even for a value you just sanitised on input.
- `esc_html()` for text, `esc_attr()` for attributes, `esc_url()` for links,
  `esc_textarea()` for textarea content, `wp_kses_post()` if HTML is genuinely
  needed.
- Never echo a raw `$_GET` / `$_POST` value.

### Authorisation

- `current_user_can()` on every screen and every handler, using a BizWit
  capability — **never `manage_options`**, which would force every bookkeeper to
  be a site administrator.
- Nonce **and** capability. A nonce proves intent; a capability proves
  authorisation. Neither substitutes for the other.
- Check capability as each role, not just as administrator, when testing.

### Data handling

- Personal data (names, NIK, NPWP, addresses, phone numbers) is covered by
  UU PDP. Do not log it, do not send it anywhere, do not add analytics.
- Internal notes are marked "never shown to the client" in the UI. Any feature
  that could expose them — export, print, email — must honour that.
- No outbound HTTP requests. BizWit does not phone home.

## Reviewing a change

Before merging anything that touches data:

- [ ] Every query prepared, no interpolated identifiers from input
- [ ] Every state change has a nonce check as its first statement
- [ ] Every screen and handler checks a BizWit capability
- [ ] Every output escaped at the point of output
- [ ] New input goes through repository sanitisation, not straight to `$wpdb`
- [ ] No new capability defaults to `manage_options`
- [ ] No personal data logged or transmitted
- [ ] `./vendor/bin/phpcs` clean — the WordPress standard catches a lot of this
- [ ] `./vendor/bin/phpstan analyse --memory-limit=1G` clean

## For site owners

BizWit runs inside WordPress and inherits its security posture. The plugin cannot
protect data on a site that is itself compromised:

- Keep WordPress, PHP and this plugin updated.
- Grant **BizWit Staff** rather than administrator to anyone who only maintains
  records. Staff can manage clients and projects but sees no financial totals.
- Grant **BizWit Manager** only to those who should see revenue.
- Use strong passwords and two-factor authentication on every account with a
  BizWit capability.
- Serve the site over HTTPS. Without it, every invoice and NPWP crosses the
  network in plain text.
- Back up your database. BizWit stores records; it is not a backup system.
- Restrict database user privileges to what WordPress needs.
