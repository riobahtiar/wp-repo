# Security self-review — WP BizWit 1.0

**Date:** 2026-07-27  
**Scope:** Plugin code under `plugins/wp-bizwit/` (beta → 1.0 candidate)  
**Reviewer:** Maintainer self-review against [SECURITY.md](../SECURITY.md)  
**Status:** Complete for ship; **external review still recommended** post-1.0 for high-risk installs

## Checklist (SECURITY.md)

| Rule | Result | Notes |
|------|--------|--------|
| Every query prepared; no interpolated identifiers from input | ✅ | `$wpdb->prepare` in repositories; `safe_orderby()` whitelists |
| Every state change has nonce as first auth step | ✅ | `check_admin_referer` / bulk nonces on all write handlers |
| Every screen/handler checks a BizWit capability | ✅ | `Screen::render_page()` + `on_load()` re-check; not `manage_options` |
| Every output escaped at point of output | ✅ | Views use `esc_html` / `esc_attr` / `esc_url`; print renderer escapes fields |
| Input via repository sanitisation | ✅ | Screens pass `wp_unslash( $_POST )` into repositories |
| No new cap defaults to `manage_options` | ✅ | Six purpose-built caps + template CPT primitives |
| No personal data logged or transmitted | ✅ | Activity log stores summaries + field keys only; no outbound HTTP |
| PHPCS (WPCS) clean | ✅ | Gate in development |
| PHPStan level 6 clean | ✅ | Gate in development |

## Threat model spot-checks

| Threat | Verified |
|--------|----------|
| SQL injection via list `orderby` | Whitelist tests (`MassAssignmentTest`) |
| CSRF on forms | Nonce on save/delete/bulk/settings |
| Privilege escalation | Staff lacks settings/reports; screen auth tests |
| Mass assignment | Unknown keys dropped in sanitise |
| Open redirect | `wp_safe_redirect` only |
| Race on invoice numbers | `Sequence::next` atomic UPSERT; uniqueness tests |
| REST anonymous access | Health requires any BizWit cap |

## Residual risk (accepted for 1.0)

1. **GET delete/void with per-item nonce** — intentional progressive enhancement; still CSRF-bound by nonce. Prefer POST for new destructive UI.
2. **Activity summaries include display names** — operational necessity for audit; not full addresses/NPWP.
3. **No WAF / rate limiting** — inherits host WordPress hardening.
4. **External adversarial review** not yet performed — track as post-1.0 for enterprise.

## Sign-off

Self-review closed for 1.0 GA criteria in plan 06 (internal). External review remains open as a continuous improvement item, not a hard block when the checklist above is green and tests pass.
