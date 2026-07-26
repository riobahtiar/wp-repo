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

Think of this like a JS monorepo (`apps/` / `packages/`), except WordPress
**requires** plugins to live under `plugins/<slug>/` and themes under
`themes/<slug>/`. Those directories *are* the packages.

---

## Packages

| Path | Package | Description | Status |
|------|---------|-------------|--------|
| [`plugins/wp-bizwit/`](plugins/wp-bizwit) | `wp-bizwit` | Clients, projects, invoices, payment receipts inside wp-admin. Indonesia-first (NPWP, PKP/PPN, kwitansi + terbilang, …). | **0.3.0 beta** |

Only `plugins/wp-bizwit` is tracked today. Add more by allowlisting them in
[`.gitignore`](.gitignore) and giving each a `package.json` so Turbo can see it.

### WP BizWit

Lightweight back office for Indonesian companies and UMKM. Record-keeping only —
it never processes or holds money.

```bash
cd plugins/wp-bizwit
composer install && npm install && npm run build
```

Full docs: [plugin README](plugins/wp-bizwit/README.md) ·
[docs/](plugins/wp-bizwit/docs) · [AGENTS](plugins/wp-bizwit/AGENTS.md) ·
[plans](plugins/wp-bizwit/plans).

---

## Quick start

### Prerequisites

- WordPress install with this folder as `wp-content` (or a symlink)
- PHP 8.0+, Composer, Node 20+
- Optional: WP-CLI, Herd, Dbngin (this machine’s default stack)

### Install tooling

From the **repo root** (`wp-content/`):

```bash
npm install
cd plugins/wp-bizwit && composer install && cd ../..
npm run build
```

Activate in WordPress:

```bash
wp plugin activate wp-bizwit
```

### Day-to-day commands

| Command | What it does |
|---------|----------------|
| `npm run build` | Build all packages (Vite assets, …) via Turbo |
| `npm run dev` | Package dev servers (e.g. Vite HMR for BizWit) |
| `npm run typecheck` | TS/Vue typecheck across packages |
| `npm run test:unit` | JS unit tests (Vitest) |
| `npm run lint` / `npm run phpcs` | PHPCS (WordPress CS) per package |
| `npm run phpstan` | PHPStan per package |
| `npm run check` | typecheck + lint + phpstan + unit + build |
| `npm run bizwit -- <script>` | Run a script only in `wp-bizwit` |
| `turbo run build --filter=wp-bizwit` | Same, Turbo filter syntax |

PHPUnit for BizWit needs Docker/`wp-env`:

```bash
npm run -w wp-bizwit test:php
```

### Vite HMR (BizWit)

In local `wp-config.php` only:

```php
define( 'WP_BIZWIT_VITE_DEV', true );
```

Then `npm run dev` (or `npm run -w wp-bizwit dev`) and open a BizWit screen.

---

## Repository layout

```
wp-content/                 ← git root (this monorepo)
  package.json              ← workspaces + turbo scripts
  turbo.json
  AGENTS.md                 ← monorepo conventions for humans & agents
  .github/workflows/        ← CI (root only — GitHub ignores nested workflows)
  plugins/
    wp-bizwit/              ← first package
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

1. **Allowlist gitignore** — root `.gitignore` ignores everything, then
   re-includes root tooling and `plugins/wp-bizwit/`. New packages need an
   explicit `!/plugins/<slug>/` line.
2. **npm workspaces** — `plugins/*` packages with a `package.json` are
   workspaces. Install once at the root.
3. **Turborepo** — `build`, `dev`, `typecheck`, `test:unit`, `lint`, `phpstan`
   run per package with caching where it helps (especially frontend builds).
4. **Per-package Composer** — PHP deps stay inside each plugin (`vendor/` is
   gitignored). No single root `composer.json` for production code (avoids
   autoload clashes with WordPress).
5. **CI at repo root** — path filters + Turbo `--filter` keep jobs cheap as more
   packages appear.

---

## Adding another plugin

1. Create `plugins/my-plugin/` with its WordPress bootstrap and tooling.
2. Add a `package.json` with at least `"name": "my-plugin"` and scripts you want
   Turbo to run (`build`, `typecheck`, …).
3. Allowlist in `.gitignore`:
   ```gitignore
   !/plugins/my-plugin/
   ```
4. Document it in this README and `AGENTS.md`.
5. Extend `.github/workflows/` if it needs PHPUnit, deploy zips, or path filters.

Shared PHP libraries (if you ever need them) should still be consumed via
Composer + Strauss *inside* each plugin, or as a private Composer package —
not as a loose folder WordPress will not load.

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
