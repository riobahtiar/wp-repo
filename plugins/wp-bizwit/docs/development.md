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
| `npm run dev` | Vite HMR dev server for Vue product UI |
| `npm run build` | Typecheck, clean `build/`, webpack legacy assets, then Vite (dual pipeline) |
| `npm run build:legacy` | Webpack / `@wordpress/scripts` only (`wp-bizwit-admin.*`, blocks) |
| `npm run build:assets` | Vite production build only (no clean, no webpack) |
| `npm run start:legacy` | Watch mode for webpack legacy assets |
| `./vendor/bin/phpcs` | WordPress Coding Standards |
| `./vendor/bin/phpcbf` | Auto-fix coding standard violations |
| `./vendor/bin/phpstan analyse --memory-limit=1G` | Static analysis at level 6 |
| `npm run test:unit` | Vitest unit tests (e.g. `formatMoney` in `resources/app/lib/`) |
| `npm run test:php` | PHPUnit suite in the wp-env container |

### Dual-pipeline assets (temporary)

Until plan 07 Phase 6 retires webpack, **both** toolchains write into `build/`:

1. `prebuild:clean` removes `build/` once.
2. `build:legacy` emits `wp-bizwit-admin.js` / `.css` / `.asset.php` (and any blocks) — still enqueued by PHP today.
3. Vite builds multi-entry product UI with `emptyOutDir: false` so it **adds** hashed files under `build/assets/` and writes **`build/manifest.json`** without wiping the webpack output.

Do not run a bare `vite build` with `emptyOutDir: true` against a populated `build/` — that deletes the legacy assets PHP still loads.

### PHP enqueue (Vite product UI)

`src/Admin/Assets.php` is the bridge from WordPress to the Vite build:

| Concern | Detail |
|---------|--------|
| Manifest | `build/manifest.json` (Vite `build.manifest: 'manifest.json'`) |
| Logical entries | `admin` → `resources/admin/main.ts`, `dashboard` → `resources/screens/dashboard.ts` |
| When | `admin_enqueue_scripts` priority 110; only on BizWit screens (`page` starts with `wp-bizwit`) |
| Dashboard | Enqueues the `dashboard` entry only |
| Handles | `wp-bizwit/{entry}` scripts (type=module) + matching styles |
| Config | `wpBizwitConfig`: `restUrl`, `restNonce`, `pluginUrl`, `version`, `locale`, `region`, `currency` |
| Missing build | Soft-fail; with `WP_DEBUG` admins see a notice to run `npm run build` |
| Legacy | Webpack `wp-bizwit-admin` still enqueued separately until Phase 6 |

### REST API (`wp-bizwit/v1`)

Custom routes for the Vue admin shell. Cookie auth + `X-WP-Nonce` (`wp_rest`).
Conventions: [frontend-architecture.md](frontend-architecture.md#rest-api-conventions).

| Concern | Detail |
|---------|--------|
| Namespace | `wp-bizwit/v1` |
| Registration | Controllers under `src/Rest/Controllers/`; wired on `rest_api_init` from `WP_BizWit::define_rest_hooks()` |
| Base class | `src/Rest/Rest_Controller.php` — `API_NAMESPACE`, `permission_any_cap()` |
| Health | `GET /wp-json/wp-bizwit/v1/health` → `{ ok, version, region }` (any BizWit cap) |
| TS client | `resources/app/api/client.ts` (`apiGet`) + `resources/app/types/window.d.ts` |
| Tests | `tests/php/RestHealthTest.php` |

### Design system (`resources/ui/`)

Shared Vue SFCs for product UI. Import with the `@ui/*` path alias:

```ts
import Button from '@ui/Button.vue';
import MoneyText from '@ui/MoneyText.vue';
import EmptyState from '@ui/EmptyState.vue';
import AppShell from '@ui/AppShell.vue';
```

| Piece | Location |
|-------|----------|
| Tokens | CSS variables on `.wp-bizwit` in `resources/styles/admin.css` |
| Money format | `resources/app/lib/money.ts` → `formatMoney(amountMinor, currency)` |
| Money tests | `resources/app/lib/money.test.ts` via `npm run test:unit` |
| Dashboard pilot | `resources/screens/DashboardApp.vue` mounted from `dashboard.ts` |

Domain props for money are always `amountMinor` + `currency`. Do not invent a
second client formatter that drifts from PHP `Support\Money`.

#### Adding a REST controller

1. Create `src/Rest/Controllers/Your_Controller.php` extending `Rest_Controller`.
2. Implement `register_routes()` with `register_rest_route( self::API_NAMESPACE, … )`.
3. Set a real `permission_callback` — use a specific `Capabilities::*` constant for
   resource routes; use `permission_any_cap()` only for shared shell endpoints.
4. In the callback, load data via a **repository** (never `$wpdb` from REST).
5. Wire it in `WP_BizWit::define_rest_hooks()`:

   ```php
   $this->loader->add_action( 'rest_api_init', new Your_Controller(), 'register_routes' );
   ```

6. Call it from Vue with `apiGet( 'your-path' )` (path is relative to `restUrl`).
7. Add PHPUnit coverage when the route is non-trivial; smoke with:

   ```bash
   wp eval 'wp_set_current_user(1); $r = rest_do_request(new WP_REST_Request("GET","/wp-bizwit/v1/health")); echo $r->get_status()," ", wp_json_encode($r->get_data()),"\n";'
   ```

#### Optional Vite HMR (dev only)

Default is **off**. In `wp-config.php` (local only):

```php
define( 'WP_BIZWIT_VITE_DEV', true );
// Optional if the dev server is not on the default origin:
// define( 'WP_BIZWIT_VITE_ORIGIN', 'http://localhost:5173' );
```

Then run `npm run dev` and load a BizWit screen. Production and wordpress.org builds must **never** set this constant — they always load hashed files from `build/` via the manifest.

### CSS scope strategy

Tailwind v4 utilities are nested under `.wp-bizwit` in `resources/styles/admin.css` (no global preflight). App markup must live inside `<div class="wrap wp-bizwit">`. Theme tokens as CSS variables are fine on the wrapper; never restyle `#wpadminbar` / `#adminmenu`. Details: [frontend-architecture.md](frontend-architecture.md).

Interactive UI stack, performance budgets and REST conventions:
[frontend-architecture.md](frontend-architecture.md).

## Layout

```
src/
  Database/       Schema, versioned Installer, atomic Sequence
  Localization/   Region (base), Indonesia, Generic_Region, Regions, Terbilang
  Repositories/   All $wpdb access. Repository (base) + one class per entity
  Support/        Money, Settings, Capabilities
  Rest/           REST base + Controllers (wp-bizwit/v1)
  Admin/
    Menu.php      Registers pages, wires each screen's load- hook
    Assets.php    Enqueues Vite entries from build/manifest.json (screen-scoped)
    Screens/      One class per screen, extending Screen
    Tables/       WP_List_Table subclasses
    Views/        Templates. Receive a single $data array — no extract()
resources/        Vue/Vite entries (admin, screens, app/api, styles) + legacy webpack SCSS/JS
build/            Dual output: webpack handle files + Vite assets + manifest.json
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
- `tests/php/RestHealthTest.php` — `GET /wp-bizwit/v1/health` auth and payload.

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
