# Plan 07 — Frontend modernization

**Status:** In progress (Phase 1 tooling done) · **Target:** foundation in 0.3.x / 0.4.x · **Blocks:** heavy
UI for 0.4+ features · **Architecture:** [`../docs/frontend-architecture.md`](../docs/frontend-architecture.md)

> **For agentic workers:** implement phase-by-phase. Each phase must leave the
> plugin installable, PHPCS/PHPStan clean, and admin screens loading. Prefer
> small commits per task. Do not introduce Livewire, Acorn, or a second UI
> framework. Read [`../docs/frontend-architecture.md`](../docs/frontend-architecture.md)
> before coding.

**Goal:** Replace the thin jQuery/`@wordpress/scripts` admin assets with a
**Vue 3 + Vite 8 + Tailwind v4** toolchain, a scoped design-system home, REST
plumbing, and a performance budget — so Projects / Invoices / Payments can ship
as rich, fast, reusable UI while remaining wordpress.org–safe.

**Architecture:** PHP keeps domain, capabilities, repositories and page shells.
Vue mounts only on BizWit screens that need it (islands / multi-entry). Assets
are built into `build/` and enqueued locally. No Laravel runtime in the plugin.

**Tech stack:** Vue 3, TypeScript, Vite 8 (Rolldown), Tailwind CSS v4, OXC via
Vite, custom `wp-bizwit/v1` REST, existing PHP backend.

---

## Why it is not trivial

**wordpress.org is a packaging constraint, not a slogan.** App JS/CSS must ship
inside the plugin zip; CDNs for app code are out; source or build tooling must
be public; we must not re-bundle libraries WordPress already registers.

**wp-admin is a hostile CSS environment.** Unscoped Tailwind preflight will
break core admin UI. Every style must be confined under `.wp-bizwit`.

**Two systems during migration.** Clients and settings already work in PHP.
A big-bang rewrite delays Projects and risks regressions. Foundation + pilot
first; migrate old screens only when justified.

**Performance is a product feature for remote users.** Chatty Livewire-style
round-trips and 300 KB UI kits fail the audience. Budgets are acceptance
criteria, not aspirations.

**Money and region must not fork.** Client-side formatters that disagree with
`Support\Money` and `Localization\Indonesia` will print wrong invoices. Domain
props (`amount_minor`) and shared rules are mandatory.

**Asset enqueue must be screen-aware.** Loading the invoice editor on every
BizWit page destroys the budget. Multi-entry + conditional enqueue is required.

---

## Scope

### In scope

- Decision record: `docs/frontend-architecture.md` (done when this plan lands).
- Vite 8 + Vue 3 + TS + Tailwind v4 pipeline; production output under `build/`.
- Replace (or dual-run then remove) current webpack/`@wordpress/scripts` admin
  entry for the product UI. Keep `@wordpress/scripts` only if blocks return.
- Scoped CSS strategy under `.wp-bizwit`.
- PHP helpers to enqueue Vite-built assets with correct deps/version/nonces.
- Minimal `wp-bizwit/v1` REST bootstrap (health + one read endpoint for pilot).
- Design-system seed: tokens, `MoneyText`, `Button`, `EmptyState`, `AppShell`.
- Pilot mount on one admin surface (recommended: Dashboard enhanced panel **or**
  a dedicated “UI lab” screen behind `WP_DEBUG` / capability — see tasks).
- Bundle size measurement script + documented budgets.
- Update `docs/development.md`, `AGENTS.md`, `package.json`, release notes.
- wordpress.org checklist (local assets, Plugin Check notes).

### Out of scope

- Livewire, Acorn, Inertia, React (unless a future block needs React).
- Rewriting Clients CRUD to Vue.
- Full invoice/project editors (belong to plans 01–03; they **consume** this
  foundation).
- Public-facing marketing site or customer portal.
- PWA / service workers (revisit post-1.0).
- Pinia until a real cross-screen state need appears.
- Shipping Storybook in the plugin zip (dev-only optional later).

---

## File map (target)

```
wp-bizwit/
├── docs/frontend-architecture.md     # decisions (this program’s constitution)
├── plans/07-frontend-modernization.md
├── package.json                      # vite, vue, tailwind, typescript
├── vite.config.ts
├── tsconfig.json
├── tsconfig.node.json
├── resources/
│   ├── styles/
│   │   └── admin.css                 # @import "tailwindcss"; scoped strategy
│   ├── admin/
│   │   └── main.ts                   # core admin entry (shared)
│   ├── screens/
│   │   ├── dashboard.ts              # entry: dashboard island
│   │   └── pilot.ts                  # entry: pilot / lab (optional)
│   ├── app/
│   │   ├── create-app.ts             # createApp + plugins
│   │   ├── api/client.ts             # fetch wrapper + nonce
│   │   └── composables/
│   └── ui/                           # design system
│       ├── Button.vue
│       ├── MoneyText.vue
│       ├── EmptyState.vue
│       └── AppShell.vue
├── build/                            # generated — git policy per release needs
│   ├── manifest.json                 # Vite manifest
│   └── assets/*
├── src/
│   ├── Admin/
│   │   ├── Assets.php                # enqueue from Vite manifest
│   │   └── Screens/…                 # mount points in views
│   └── Rest/
│       ├── Rest_Controller.php
│       └── Controllers/Health_Controller.php
└── tests/php/
    └── RestHealthTest.php            # or bootstrap smoke
```

---

## Phases and tasks

Phases are ordered so each leaves a green tree. Do not start Phase N+1 if Phase
N acceptance fails.

### Phase 0 — Lock decisions (docs only)

- [x] Confirm `docs/frontend-architecture.md` is linked from `docs/index.md`
      and `AGENTS.md`.
- [x] Index this plan in `plans/README.md` and note it on `plans/PROGRESS.md`.
- [x] Record in CHANGELOG Unreleased: frontend architecture agreed (docs).
- [x] Cross-link UI approach on plans 01–04.
- [ ] Commit when ready: `docs: agree Vue/Vite/Tailwind frontend architecture`

**Acceptance:** Any new contributor finds “no Livewire/Acorn” and the stack in
under two minutes from `docs/index.md`. **Met** (pending optional commit).

---

### Phase 1 — Tooling bootstrap

**Goal:** `npm run build` produces Vite assets; `npm run dev` runs Vite dev
server for HMR during local work.

- [x] **1.1** Add dependencies (dev unless noted):

  ```bash
  cd wp-content/plugins/wp-bizwit
  npm install -D vite@^8 vue-tsc typescript @vitejs/plugin-vue tailwindcss @tailwindcss/vite
  npm install vue
  ```

  Pin versions that satisfy the project dependency policy (maintained, recent).
  Do **not** add Vuetify/Element Plus.

- [x] **1.2** Create `vite.config.ts`:

  - `plugins`: `@vitejs/plugin-vue`, `@tailwindcss/vite`
  - `build.manifest`: `'manifest.json'` → `build/manifest.json` (not `.vite/`)
  - `build.outDir`: `build`
  - `build.emptyOutDir`: **false** — dual-pipeline with webpack; clean once in npm script
  - `build.rollupOptions.input`: multi-entry map
    - `admin`: `resources/admin/main.ts`
    - `dashboard`: `resources/screens/dashboard.ts` (can be stub)
  - `base`: production empty or plugin-relative; document dev vs prod
  - WordPress plugin path: ensure built file URLs work with
    `plugins_url( 'build/…', WP_BIZWIT main file )`

  Example shape (adjust to final paths):

  ```ts
  import { defineConfig } from 'vite';
  import vue from '@vitejs/plugin-vue';
  import tailwindcss from '@tailwindcss/vite';
  import path from 'node:path';

  export default defineConfig( {
    plugins: [ vue(), tailwindcss() ],
    root: __dirname,
    base: './',
    build: {
      outDir: 'build',
      emptyOutDir: false, // preserve webpack output after dual-pipeline clean
      manifest: 'manifest.json',
      rollupOptions: {
        input: {
          admin: path.resolve( __dirname, 'resources/admin/main.ts' ),
          dashboard: path.resolve( __dirname, 'resources/screens/dashboard.ts' ),
        },
      },
    },
    resolve: {
      alias: {
        '@': path.resolve( __dirname, 'resources' ),
        '@ui': path.resolve( __dirname, 'resources/ui' ),
      },
    },
  } );
  ```

- [x] **1.3** Add `tsconfig.json` / `tsconfig.node.json` with `strict: true`,
      paths for `@/*` and `@ui/*`.

- [x] **1.4** Add `resources/styles/admin.css`:

  ```css
  @import "tailwindcss";

  /*
   * Scope strategy: document the chosen approach in development.md.
   * Required outcome: styles under .wp-bizwit only; no global admin breakage.
   * Preferred: @source content paths + a wrapper; disable full preflight on
   * body if Tailwind v4 allows important selector / custom preflight limit.
   */
  .wp-bizwit {
    /* design tokens as CSS variables if needed */
  }
  ```

- [x] **1.5** Stub entries:

  `resources/admin/main.ts` — import styles only or register nothing.  
  `resources/screens/dashboard.ts` — `createApp` mount helper import, no-op if
  `#wp-bizwit-dashboard` missing. (render function, not runtime `template:`)

- [x] **1.6** Update `package.json` scripts:

  ```json
  {
    "dev": "vite",
    "prebuild:clean": "node -e \"require('fs').rmSync('build',{recursive:true,force:true})\"",
    "build": "vue-tsc --noEmit && npm run prebuild:clean && npm run build:legacy && vite build",
    "build:assets": "vite build",
    "build:legacy": "wp-scripts build --blocks-manifest",
    "preview": "vite preview"
  }
  ```

  Keep `env:*` and `test:php`. Retire or gate old `wp-scripts` `start`/`build`
  once Vite replaces admin assets (Phase 2). Until then, rename clearly:
  `build:legacy` vs `build` to avoid ambiguity.

- [x] **1.7** `.gitignore`: ensure `node_modules/` ignored; decide whether
      `build/` is committed (recommended for wordpress.org release branches /
      tags: **commit built assets on release**, or build in CI before deploy —
      match existing `.github/workflows/deploy.yml` which already builds).

- [x] **1.8** Run `npm run build` — expect `build/manifest.json` and hashed
      assets. Commit tooling + stubs.

**Acceptance:**

- `npm run build` exits 0.
- No Livewire/Acorn packages.
- PHPCS/PHPStan still green (PHP untouched or only trivial).
- Dual-pipeline: both webpack handle files and Vite `manifest.json` + `assets/` present.

---

### Phase 2 — PHP asset bridge

**Goal:** WordPress enqueues Vite production builds from the manifest; only on
BizWit screens.

- [x] **2.1** Create `src/Admin/Assets.php`:

  Responsibilities:

  - Read `build/manifest.json` (or Vite 8 manifest location).
  - `enqueue_entry( string $entry, array $extra_script_deps = array() ): void`
  - Register script handle `wp-bizwit/{entry}` and matching style if present.
  - `wp_localize_script` or `wp_add_inline_script` with:

    ```php
    array(
      'restUrl'   => esc_url_raw( rest_url( 'wp-bizwit/v1' ) ),
      'restNonce' => wp_create_nonce( 'wp_rest' ),
      'pluginUrl' => WP_BIZWIT_URL,
      'version'   => WP_BizWit::PLUGIN_VERSION,
      'locale'    => get_user_locale(),
      // region code from settings — not interface locale
      'region'    => /* Regions::current id */,
      'currency'  => /* from settings */,
    );
    ```

  - Fail soft if manifest missing (admin notice for admins when `WP_DEBUG`).

- [x] **2.2** Wire `Assets` from `WP_BizWit::define_admin_hooks()` (or `Menu`)
      instead of/in addition to raw `enqueue_entrypoint()` for new entries.

- [x] **2.3** Screen-conditional load:

  - Dashboard screen → enqueue `dashboard` entry only.
  - Later: Projects → `projects` entry, etc.
  - Never enqueue all entries on `admin_enqueue_scripts` globally.

- [x] **2.4** Dev mode (optional but valuable): if `defined('WP_BIZWIT_VITE_DEV')
      && WP_BIZWIT_VITE_DEV`, load from `http://localhost:5173/@vite/client` and
      entry TS. Document in `docs/development.md`. Default **off** so production
      and wordpress.org never hit localhost.

- [x] **2.5** Remove jQuery `ProvidePlugin` dependency from new entries. Leave
      legacy webpack path only until deleted. (Phase 1 already had no jQuery on Vite entries.)

- [ ] **2.6** Update deploy workflow if needed so `npm run build` uses Vite.

- [x] **2.7** Smoke:

  ```bash
  npm run build
  wp eval 'echo file_exists( WP_BIZWIT_PATH . "build/manifest.json" ) ? "ok\n" : "missing\n";'
  ```

  Open Dashboard in browser: no console 404s for assets.

**Acceptance:**

- Dashboard (or pilot) loads Vite CSS/JS with 200s.
- Non-BizWit admin pages do not load Vue.
- Assets are local paths under the plugin URL.

---

### Phase 3 — REST foundation

**Goal:** Vue can call a versioned REST API with capability checks.

- [x] **3.1** Create `src/Rest/Rest_Controller.php` + register in `WP_BizWit`
      on `rest_api_init`.

- [x] **3.2** `GET /wp-json/wp-bizwit/v1/health`

  - `permission_callback`: any BizWit capability via
    `Capabilities::current_user_has_any()` / `Rest_Controller::permission_any_cap()`.
  - Response:

    ```json
    {
      "ok": true,
      "version": "0.3.0",
      "region": "id"
    }
    ```

    (`region` is `Regions::current()->code()`, e.g. `id` or `generic`.)

- [x] **3.3** Add `resources/app/api/client.ts` + `resources/app/types/window.d.ts`
      aligned with `wpBizwitConfig` from Assets.php.

- [x] **3.4** PHPUnit `RestHealthTest` + `wp eval` smoke for health route.

- [x] **3.5** Document how to add a controller in `docs/development.md`
      (conventions already in `docs/frontend-architecture.md`).

**Acceptance:**

- Unauthenticated → 401/403.
- Admin with cap → 200 + version.
- Frontend client can log health JSON on pilot screen (Phase 5).

**Do not** yet expose write routes for invoices without plan 02.

---

### Phase 4 — Design system seed

**Goal:** Shared components other screens will import.

- [ ] **4.1** Tokens in CSS (spacing, radii, semantic colors that work on
      wp-admin gray). Prefer existing WP admin blues for primary actions so the
      plugin feels native.

- [ ] **4.2** Components (Vue 3 + `<script setup lang="ts">`):

  | Component | Props (minimum) | Notes |
  |-----------|-----------------|-------|
  | `Button.vue` | `variant`, `size`, `disabled`, `type` | Focus ring; no bare div buttons |
  | `MoneyText.vue` | `amountMinor: number`, `currency: string` | Format IDR as `Rp 1.500.000` (no decimals); other currencies later via rules from region config bootstrapped in localize |
  | `EmptyState.vue` | `title`, `description`, default slot for actions | Indonesian-friendly copy samples |
  | `AppShell.vue` | `title`, `description?` | Wraps page header inside `.wp-bizwit` |

- [ ] **4.3** `MoneyText` unit test via Vitest **or** pure TS formatter tests:

  - Prefer extracting `formatMoney( amountMinor, currency, region )` to
    `resources/app/lib/money.ts` and testing without full Vue mount.
  - Cases: `1500000` + `IDR` → contains `1.500.000` and `Rp`; never `1,500,000.00`.

- [ ] **4.4** Add Vitest only if formatter tests need it:

  ```bash
  npm install -D vitest
  ```

  Script: `"test:unit": "vitest run"`. Keep optional if pure node assert is enough.

- [ ] **4.5** Document component import path `@ui/Button.vue` in AGENTS.md.

**Acceptance:**

- Format tests pass.
- Pilot screen renders all four components without layout breaking wp-admin.

---

### Phase 5 — Pilot mount

**Goal:** Prove the full path: PHP screen → enqueue → Vue mount → REST → UI.

**Recommended pilot (pick one, do not do both in the first PR):**

| Option | Pros | Cons |
|--------|------|------|
| **A. Dashboard island** | Real screen; users see value | Touches production dashboard |
| **B. Hidden UI lab page** | Safe | Extra menu item; remove or gate before .org |

Default recommendation: **A** with a small card “Status” that calls `/health`
and shows `MoneyText` sample using settings currency — progressive, low risk.

- [ ] **5.1** Update `Admin/Views/dashboard.php` (or Dashboard_Screen data) to
      include:

  ```php
  <div class="wp-bizwit" id="wp-bizwit-dashboard" data-boot="<?php echo esc_attr( wp_json_encode( $boot ) ); ?>"></div>
  ```

- [ ] **5.2** `resources/screens/dashboard.ts` mounts `DashboardApp.vue` on
      `#wp-bizwit-dashboard` if present.

- [ ] **5.3** `DashboardApp.vue`: EmptyState or card + health fetch + MoneyText
      demo amount.

- [ ] **5.4** Manual test checklist:

  - [ ] PHP 8.x + Herd `http://wp.test` dashboard loads
  - [ ] No CSS leakage on Posts list (spacing/fonts intact)
  - [ ] Slow 3G: card shows skeleton then data
  - [ ] User without BizWit cap cannot call health

- [ ] **5.5** Indonesian strings: any user-visible pilot strings in `__()`,
      translated in `languages/wp-bizwit-id_ID.po`, compile mo/php.

**Acceptance:** Pilot is demo-quality and safe; can ship in a minor beta.

---

### Phase 6 — Retire legacy admin webpack entry

- [ ] **6.1** Move any needed legacy SCSS rules into Tailwind or plain CSS under
      `.wp-bizwit`.
- [ ] **6.2** Remove `webpack.config.js` admin entries **or** entire webpack if
      no blocks.
- [ ] **6.3** Drop `@wordpress/scripts` if unused; keep `@wordpress/env` for
      tests.
- [ ] **6.4** Delete empty `resources/admin/js/app.js` jQuery stub if replaced.
- [ ] **6.5** Update `WP_BizWit::enqueue_entrypoint` — delete or narrow to
      Assets.php only.
- [ ] **6.6** `npm run build` + smoke all admin pages.

**Acceptance:** Single asset pipeline; docs mention only Vite.

---

### Phase 7 — Performance gate

- [ ] **7.1** Add `npm run build:analyze` using `rollup-plugin-visualizer` or
      Vite’s built-in analyze — devDependency only.
- [ ] **7.2** Record baseline sizes in `plans/PROGRESS.md` or a short
      `docs/performance.md` table (gzipped).
- [ ] **7.3** Fail CI (optional warning first) if shared chunk > 60 KB gzip
      once measurement is stable — start with documentation, automate later.
- [ ] **7.4** Confirm code-splitting: dashboard entry does not include future
      invoice editor (when that entry exists).

**Acceptance:** Budgets from `docs/frontend-architecture.md` measured for pilot.

---

### Phase 8 — wordpress.org readiness pass

- [ ] **8.1** Confirm no CDN script/style tags for app assets.
- [ ] **8.2** List runtime npm packages in README/docs (should be tiny: `vue`).
- [ ] **8.3** Run Plugin Check Plugin against a release zip when available.
- [ ] **8.4** README.txt / deploy: `npm run build` before `wp dist-archive`.
- [ ] **8.5** LICENSE note for Vue (MIT) compatibility with GPLv2+.

**Acceptance:** Reviewer-facing notes exist; zip includes `build/` assets.

---

### Phase 9 — Handoff to feature plans

- [ ] Update `plans/01-projects.md` with a short “UI” section: termin builder
      may use Vue island; list can stay `WP_List_Table` initially.
- [ ] Update `plans/02-invoices.md`: line-item editor is a Vue screen; budgets
      apply.
- [ ] Update `plans/03-payments-receipts.md`: allocation UI Vue; print PHP.
- [ ] Update `plans/04-ux-and-onboarding.md`: empty states use `@ui/EmptyState`.
- [ ] PROGRESS.md: mark frontend foundation status.

**Acceptance:** Feature plans reference the foundation; no conflicting stack.

---

## Performance budget (copy for acceptance)

| Asset | Max gzipped |
|-------|-------------|
| Shared / admin core | 60 KB |
| Dashboard (or pilot) chunk | 50 KB |
| CSS | 25 KB |

Measure after Phase 5 and Phase 7. New heavy screens must add their own row.

---

## Security checklist (frontend)

- [ ] REST `permission_callback` on every route
- [ ] Nonce on every authenticated browser call
- [ ] Escape mount-point data attributes built in PHP (`esc_attr`,
      `wp_json_encode`)
- [ ] No secrets in `wp_localize_script` (no API private keys)
- [ ] Capability checks still on PHP `Screen::render_page()` even if Vue is blank
- [ ] Sanitize all writes in repositories — Vue is not trusted

See [`../SECURITY.md`](../SECURITY.md).

---

## Risks and open questions

| Risk | Mitigation |
|------|------------|
| Tailwind breaks wp-admin | Scoped `.wp-bizwit`; visual QA on Posts/Pages |
| Manifest path differs Vite 7/8 | Pin Vite 8; integration test for enqueue |
| Dual pipeline confusion | Phase 6 hard-delete webpack admin |
| Scope creep into full SPA | Islands + multi-entry; no router until second Vue screen |
| Money formatter drift | Shared rules + tests; documents still PHP-formatted |
| Dev HMR hard on Herd | Document `WP_BIZWIT_VITE_DEV`; production always uses `build/` |
| Team wants Livewire later | Architecture doc rejects for .org plugin; hosted SaaS is separate |

### Open questions (decide during Phase 1–2 if blocking)

1. Commit `build/` on every main commit vs CI-only artifacts?
2. Pilot = dashboard island vs debug-only lab page?
3. Adopt a headless primitive library after size spike, or pure first-party?

---

## Acceptance criteria (plan complete)

Foundation is **done** when all of the following are true:

1. Docs state Vue/Vite/Tailwind stack and reject Livewire/Acorn for this plugin.
2. `npm run build` produces multi-entry assets consumed by PHP `Assets`.
3. At least one real admin surface mounts Vue, calls REST health, renders
   `MoneyText` correctly for IDR.
4. Styles do not break non-BizWit admin screens.
5. Bundle sizes for pilot are measured and within budget (or documented debt with
   owner and deadline).
6. Legacy jQuery admin entry is removed or scheduled with a date.
7. Feature plans 01–04 know how to consume the foundation.
8. PHPCS 0 errors, PHPStan level 6 clean, unit tests for money formatter green.
9. No production Composer/npm dependency on Livewire, Acorn, or UI mega-kits.

---

## Suggested commit sequence

1. `docs: frontend architecture + plan 07`
2. `build: add Vite Vue Tailwind toolchain`
3. `feat(admin): enqueue Vite assets from manifest`
4. `feat(rest): add wp-bizwit/v1 health endpoint`
5. `feat(ui): seed design system components`
6. `feat(dashboard): Vue pilot island`
7. `chore: remove webpack admin pipeline`
8. `docs: performance baselines + .org packaging notes`

---

## Execution notes for agents

- Work in `wp-content/plugins/wp-bizwit` only; never edit WordPress core.
- After PHP changes: `./vendor/bin/phpcs` and
  `./vendor/bin/phpstan analyse --memory-limit=1G`.
- After UI changes: `npm run build` and browser smoke on `http://wp.test`.
- Ship Indonesian strings with any user-visible pilot UI.
- Prefer extending repositories over querying `$wpdb` from REST controllers.
- If a task needs a product decision (pilot location, commit build/), stop and
  ask rather than inventing a second pattern.
