# wp-repo

**WordPress monorepo** for first-party plugins (and later themes) built for
**Indonesian businesses — companies and UMKM**.

The git root is a normal local `wp-content/` directory. WordPress core sits one
level up and is **not** versioned here. Stock themes, uploads, language packs,
and third-party plugins can exist on disk for local development; git only tracks
packages we own.

| | |
|---|---|
| Remote | `git@github.com:riobahtiar/wp-repo.git` |
| Local site | http://wp.test |
| Server | [Laravel Herd](https://herd.laravel.com) + [Dbngin](https://dbngin.com) |
| Orchestration | [npm workspaces](https://docs.npmjs.com/cli/using-npm/workspaces) + [Turborepo](https://turbo.build) |
| PHP | 8.4 local · packages target 8.0+ |
| Agent guide | [AGENTS.md](AGENTS.md) (`CLAUDE.md` is a symlink to the same file) |

Think of this like a JS monorepo (`apps/` / `packages/`), except WordPress
**requires** plugins under `plugins/<slug>/` and themes under `themes/<slug>/`.
Those directories *are* the packages.

---

## Packages

| Path | Package | Description | Status |
|------|---------|-------------|--------|
| [`plugins/wp-bizwit/`](plugins/wp-bizwit) | `wp-bizwit` | Clients, projects, invoices, payment receipts inside wp-admin. Indonesia-first (NPWP, PKP/PPN, kwitansi + terbilang, …). | **0.3.0 beta** |

Only `plugins/wp-bizwit` is tracked today. Add more by allowlisting them in
[`.gitignore`](.gitignore) and giving each a `package.json` so Turbo can see it.

---

## Monorepo workflow (industry defaults)

These rules match common npm/Turbo monorepos (Nx/Turbo docs, npm workspaces):

| Rule | Practice here |
|------|----------------|
| **One lockfile** | Only root `package-lock.json`. Never commit `plugins/*/package-lock.json`. |
| **Install from root** | Always `npm install` / `npm ci` in `wp-content/`, never only inside a plugin. |
| **Task runner** | Turborepo for build/test/typecheck across packages. |
| **Filter a package** | `npm run -w wp-bizwit <script>` or `turbo run build --filter=wp-bizwit`. |
| **PHP deps** | Still **per package** via Composer (`plugins/<slug>/composer.json`). WordPress cannot share one global `vendor/` for plugins. |
| **CI** | Root `.github/workflows/` only. Nested package workflows are not used. |
| **Releases** | Tagged `package/vX.Y.Z` (e.g. `wp-bizwit/v0.3.1`). |

### Why not a nested lockfile?

A second `package-lock.json` under a workspace:

- diverges from what CI installs (`npm ci` at root),
- can reinstall a full nested `node_modules` and break hoisting,
- is explicitly discouraged for npm workspaces.

If you accidentally run `npm install` inside a plugin, delete any new nested
lockfile and reinstall from the root.

---

## Quick start

### Prerequisites

- WordPress install with this folder as `wp-content` (or a symlink)
- PHP 8.0+, Composer, Node 20+ (see `.nvmrc`)
- Optional: WP-CLI, Herd, Dbngin (this machine’s default stack)

### Install (root only)

```bash
# From the monorepo root (wp-content/)
npm install

# PHP deps for each package you work on
cd plugins/wp-bizwit && composer install && cd ../..

npm run build
wp plugin activate wp-bizwit
```

### Day-to-day commands

| Command | What it does |
|---------|----------------|
| `npm install` | Install root + all workspaces (**only from root**) |
| `npm run build` | Turbo: build every package |
| `npm run dev` | Turbo: package dev servers (Vite HMR) |
| `npm run typecheck` | TS/Vue typecheck |
| `npm run test:unit` | Vitest unit tests |
| `npm run lint` / `npm run phpcs` | PHPCS per package |
| `npm run phpstan` | PHPStan per package |
| `npm run check` | Full local gate (typecheck + lint + phpstan + unit + build + budgets) |
| `npm run -w wp-bizwit -- <script>` | Run a script only in `wp-bizwit` |
| `npm run bizwit -- <script>` | Shortcut for the line above |
| `turbo run build --filter=wp-bizwit` | Same, Turbo filter syntax |

PHPUnit (needs Docker / wp-env):

```bash
npm run -w wp-bizwit test:php
```

### Vite HMR (BizWit)

In local `wp-config.php` only:

```php
define( 'WP_BIZWIT_VITE_DEV', true );
```

Then from monorepo root:

```bash
npm run -w wp-bizwit dev
```

Open a BizWit screen in wp-admin.

---

## Repository layout

```
wp-content/                 ← git root (this monorepo)
  package.json              ← workspaces + turbo scripts
  package-lock.json         ← ONLY lockfile for JS
  turbo.json
  AGENTS.md
  .github/workflows/        ← CI + release (root only)
  plugins/
    wp-bizwit/              ← first package (own composer.json)
      src/ resources/ docs/ plans/ …
  themes/                   ← not tracked yet (stock themes stay local-only)
  uploads/ languages/ …     ← gitignored host state
```

WordPress install root (parent) still holds core + install-wide notes:

- `../README.md` — Herd/Dbngin environment
- `../AGENTS.md` — security, `dbDelta`, WP-CLI, Indonesia-first product rules
- `../SECURITY.md` — security policy for code in this install

---

## How the monorepo works

1. **Allowlist gitignore** — ignore everything, re-include root tooling and
   `plugins/wp-bizwit/`. New packages need an explicit `!/plugins/<slug>/` line.
2. **npm workspaces** — `plugins/*` packages with a `package.json` join the
   workspace graph. Dependencies hoist to the root `node_modules/`.
3. **Turborepo** — `build`, `dev`, `typecheck`, `test:unit`, `lint`, `phpstan`,
   `check:bundle-size` with caching; invalidated by root lockfile changes.
4. **Per-package Composer** — PHP deps stay inside each plugin (`vendor/`
   gitignored). No root `composer.json` for production plugins.
5. **CI at repo root** — `npm ci` once; Turbo for JS; Composer jobs per package
   with `working-directory`.

---

## Adding another plugin

1. Create `plugins/my-plugin/` with its WordPress bootstrap and tooling.
2. Add a `package.json` with `"name": "my-plugin"` and scripts Turbo should run
   (`build`, `typecheck`, `test:unit`, …). **Do not** add a nested
   `package-lock.json`.
3. Allowlist in `.gitignore`:
   ```gitignore
   !/plugins/my-plugin/
   ```
4. From root: `npm install` (updates the **root** lockfile), then document the
   package in this README and `AGENTS.md`.
5. Extend `.github/workflows/ci.yml` (path filters / PHP jobs) and add a release
   workflow if the package ships zips (`my-plugin/v*`).

Shared PHP libraries (if ever needed) go through Composer + Strauss *inside*
each plugin, or a private Composer package — not a loose folder WordPress will
not load.

---

## Release (wp-bizwit)

```bash
git tag wp-bizwit/v0.3.1
git push origin wp-bizwit/v0.3.1
```

GitHub Actions builds assets from the root workspace, packs the plugin zip, and
creates a GitHub Release.

---

## Audience

Everything here targets **Indonesian businesses**. Defaults matter: rupiah,
Bahasa Indonesia as a shipped translation, domain vocabulary (faktur, kwitansi,
NPWP, PKP, PPN, …), and culture notes (single legal names, WhatsApp-first
contact, Lebaran-aware collections). See the parent install
[`AGENTS.md`](../AGENTS.md) and BizWit’s
[indonesia](plugins/wp-bizwit/docs/indonesia.md) /
[culture](plugins/wp-bizwit/docs/culture.md) docs before inventing new patterns.

---

## License

Each package carries its own license (BizWit: GPL-2.0-or-later). Root scaffolding
is available under the same terms as the packages you add unless noted otherwise.
