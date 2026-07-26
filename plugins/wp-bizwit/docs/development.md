# Development

Requires PHP 8.0+, Composer and Node.

```bash
composer install
npm install
npm run build
```

## Commands

| Command | Purpose |
|---------|---------|
| `npm run start` | *(Legacy)* Rebuild assets via `@wordpress/scripts` — retiring in [plan 07](../plans/07-frontend-modernization.md) |
| `npm run build` | Production asset build (Vite after plan 07 Phase 1) |
| `./vendor/bin/phpcs` | WordPress Coding Standards |
| `./vendor/bin/phpcbf` | Auto-fix coding standard violations |
| `./vendor/bin/phpstan analyse --memory-limit=1G` | Static analysis at level 6 |
| `npm run test:php` | PHPUnit suite in the wp-env container |

Interactive UI stack, performance budgets and REST conventions:
[frontend-architecture.md](frontend-architecture.md).

## Layout

```
src/
  Database/       Schema, versioned Installer, atomic Sequence
  Localization/   Region (base), Indonesia, Generic_Region, Regions, Terbilang
  Repositories/   All $wpdb access. Repository (base) + one class per entity
  Support/        Money, Settings, Capabilities
  Admin/
    Menu.php      Registers pages, wires each screen's load- hook
    Screens/      One class per screen, extending Screen
    Tables/       WP_List_Table subclasses
    Views/        Templates. Receive a single $data array — no extract()
resources/        SCSS and JS entry points, compiled by @wordpress/scripts
languages/        Translation template and the id_ID catalogue
tests/php/        PHPUnit tests
```

## Conventions

- **All `$wpdb` access goes through a repository.** That keeps the `phpcs:ignore`
  suppressions direct database queries require confined to a few audited methods,
  rather than scattered across screens where a missing `prepare()` is easy to
  miss in review.
- **Repositories sanitise, screens do not.** Pass raw `$_POST` straight to
  `create()` / `update()`; the repository's private `sanitize()` builds the
  column list from a hardcoded array, so an unexpected key can never reach a
  query.
- **Form handling belongs in `Screen::on_load()`**, which runs on `load-{page}`
  before any output. That is what makes `wp_safe_redirect()` possible, giving
  post/redirect/get so a refresh cannot resubmit. Never handle a POST inside
  `render()`.
- **Every screen checks its own capability.** `Screen::render_page()` does this;
  do not bypass it. Registering a menu page with a capability only hides the menu
  item — the URL stays reachable.
- **Never hardcode a user-facing field label in a view.** Use
  `$region->field_label()` with a neutral fallback, so the Indonesian profile can
  override it. See [indonesia.md](indonesia.md).
- **Feedback across a redirect** goes through `Admin\Notices`, a per-user
  transient rather than a query string.

## Translations

Indonesian (`id_ID`) ships complete — all 240 strings — and Indonesian users are
the primary audience, so **a new untranslated string is an unfinished string**.

After adding or changing any translatable text:

```bash
# 1. Refresh the template
$(command -v wp) i18n make-pot . languages/wp-bizwit.pot \
  --exclude=resources,vendor,vendor-prefixed,node_modules,tests

# 2. Merge new strings into the Indonesian catalogue
$(command -v wp) i18n update-po languages/wp-bizwit.pot languages/

# 3. Translate the new msgids in languages/wp-bizwit-id_ID.po

# 4. Compile
$(command -v wp) i18n make-mo languages/ && $(command -v wp) i18n make-php languages/
```

Compiled `.mo` and `.l10n.php` files are gitignored and built at release time.
After a fresh clone, run step 4 or the Indonesian translation will not appear.

Strings authored in Indonesian inside `Localization\Indonesia` are still wrapped
in `__()` and appear in the catalogue translated to themselves. That is
deliberate: one extraction pipeline, and a future `en_US` override could render
those screens in English if anyone wants it.

To see the translated UI, set the site language to Bahasa Indonesia:

```bash
wp language core install id_ID --activate
```

## Verifying without a browser

`wp eval` boots the full WordPress runtime and is the fastest way to exercise
plugin code. Admin screens need pieces of `wp-admin/` that WP-CLI does not load:

```bash
wp eval '
require_once ABSPATH . "wp-admin/includes/screen.php";
require_once ABSPATH . "wp-admin/includes/template.php";
require_once ABSPATH . "wp-admin/includes/class-wp-list-table.php";
$_SERVER["HTTP_HOST"] = "localhost";
set_current_screen( "toplevel_page_wp-bizwit" );
wp_set_current_user( 1 );
ob_start();
( new WP_BizWit\Admin\Screens\Clients_Screen( new WP_BizWit\Repositories\Client_Repository() ) )->render_page();
echo strlen( ob_get_clean() ) . " bytes\n";
'
```

Without `$_SERVER["HTTP_HOST"]`, `WP_List_Table` emits undefined-index warnings
from the CLI context, not from plugin code.

## Tests

- `tests/php/MoneyTest.php` — amount parsing across locale formats. **Extend it
  before touching `Money::normalize_decimal_string()`.** A separator misread by
  one position is an invoice wrong by a factor of a hundred, and nothing
  downstream catches it.
- `tests/php/IndonesiaRegionTest.php` — NPWP formatting and validation, the 38
  provinces, terbilang grammar, document numbering, bea meterai thresholds, and
  the PKP / non-PKP tax gate.

## Tooling papercuts in this project

**`composer run i18n:extract` fails** with `'i18n' is not a registered wp
command`. Composer prepends `vendor/bin` to `PATH`, so `wp` resolves to the bare
WP-CLI *framework* pulled in as a dev dependency rather than the global WP-CLI
phar that bundles the i18n commands. Use `$(command -v wp)` as shown above.

**PHPStan OOMs at its default 128M limit.** Always pass `--memory-limit=1G`. The
symptom is `Child process error (exit code 255)` from a parallel worker, which
looks like a code failure but is not.

**`phpcs.xml` scopes by PHP extension and anchors directory excludes.** PHPCS
exclude patterns are unanchored regular expressions: a pattern like `*.ts`
compiles to `.*.ts` and matches any absolute path containing "ts" — including a
checkout under `~/Projects/`, which would silently exclude every file. This
project uses `<arg name="extensions" value="php"/>` and
`type="relative"` excludes with a leading `^`. If PHPCS reports no findings, run
`./vendor/bin/phpcs -v` and confirm the "files in queue" count is non-zero.

## Dependency policy

Prefer a self-contained plugin. **Production `require` stays empty** unless a
library clearly earns its place. Runtime third-party PHP packages (when any are
added) are namespaced into `vendor-prefixed/` via Strauss so they cannot clash
with other plugins.

Dev tooling may use Composer packages when they meet all of:

1. **Widely used or well-maintained** (roughly 1,000+ dependents, or active
   maintenance by a known project/org — WordPress.org tooling, PHPStan, WPCS).
2. **Updated within the last eight months.**
3. **Trusted maintainer or reputation** for solid releases.

Do not pull scaffolding templates, “sync from upstream boilerplate” scripts, or
thin wrapper packages that we can implement in a few dozen lines of first-party
code.

**Frontend runtime:** prefer Vue only (plus tiny intentional helpers). Do not
add Livewire, Acorn, or large UI kits to the distributable plugin. See
[frontend-architecture.md](frontend-architecture.md).

**`mysql` is not on `PATH`** in the Herd + Dbngin setup this project is developed
against, so `wp db query` fails. Use `wp eval` with `$wpdb` instead.
