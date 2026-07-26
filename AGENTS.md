# WP monorepo — agent guide

This repository is the **git root for first-party WordPress work**. The working
tree is a normal `wp-content/` directory on a local WordPress install (Herd +
Dbngin at http://wp.test). Only packages listed in the root allowlist are
versioned here; stock themes, uploads, and third-party plugins stay on disk but
stay out of git.

| | |
|---|---|
| Local site | http://wp.test |
| Server | Laravel Herd (nginx + PHP-FPM) |
| Database | Dbngin (MySQL) |
| PHP | 8.4 (local) · 8.0+ supported by packages |
| Orchestration | npm workspaces + Turborepo |

Install-wide WordPress conventions that still apply when coding (security,
`dbDelta`, WP-CLI, Indonesia-first product rules) live in the **WordPress
install root** one level up: `../AGENTS.md` and `../SECURITY.md`. This file
covers monorepo layout and package boundaries.

---

## Golden rules

1. **This repo is not WordPress core.** Never add `wp-admin/`, `wp-includes/`, or
   `wp-config.php`. The parent directory is the WP install; this directory is
   only `wp-content` content we own.
2. **One package = one plugin or theme.** Put work under
   `plugins/<slug>/` or (later) `themes/<slug>/`. Do not invent a parallel
   `packages/` tree for production code that WordPress must load — WP only
   autoloads from those paths.
3. **Read the package’s own `AGENTS.md` / `CLAUDE.md` first.** It overrides
   monorepo defaults for that codebase.
4. **Ship Indonesian (`id_ID`) with the feature.** Translatable but untranslated
   is unfinished for this audience.
5. **Do not commit secrets, `uploads/`, or third-party plugins.** The root
   `.gitignore` allowlists packages; keep it that way when adding a new one.

## Packages

| Path | npm name | What it is |
|------|----------|------------|
| [`plugins/wp-bizwit/`](plugins/wp-bizwit) | `wp-bizwit` | Business admin (clients, projects, invoices, payments) for Indonesian companies & UMKM |

Add a new first-party plugin by creating `plugins/<slug>/` with its own
`package.json` (for Turbo), `composer.json` if needed, and an entry in the root
`.gitignore` allowlist (`!/plugins/<slug>/`).

## Commands (from repo root)

```bash
npm install                 # install root + all workspaces
npm run build               # turbo: build every package
npm run dev                 # turbo: package dev servers (Vite HMR, etc.)
npm run typecheck
npm run test:unit
npm run lint                # package-defined (usually PHPCS)
npm run phpstan
npm run check               # typecheck + lint + phpstan + unit + build

# Single package
npm run bizwit -- build
npm run -w wp-bizwit dev
turbo run build --filter=wp-bizwit
turbo run build --filter=...[origin/main]   # affected only (once history exists)
```

PHP dependencies are **per package** (`composer install` inside the plugin).
Root `npm` does not install Composer deps.

```bash
cd plugins/wp-bizwit && composer install
```

## Where docs live

| Layer | Location |
|-------|----------|
| Monorepo (this file + README) | repo root |
| WP install environment | `../AGENTS.md`, `../README.md` (parent of `wp-content`) |
| Package conventions | `plugins/<slug>/AGENTS.md` |
| Package product docs | `plugins/<slug>/docs/` |
| Unbuilt work | `plugins/<slug>/plans/` |

## CI shape

GitHub Actions live in **root** `.github/workflows/` (GitHub ignores nested
workflow dirs). Package-local historical workflows under
`plugins/*/.github/` are documentation only unless moved — prefer root workflows
with `working-directory` / Turbo `--filter`.

## When adding a package

1. Scaffold under `plugins/<slug>/` (or `themes/<slug>/`).
2. Allowlist it in root `.gitignore`.
3. Give it a `package.json` `name` and scripts Turbo should run (`build`,
   `typecheck`, `test:unit`, `lint`, …).
4. Document it in root `README.md` and this file.
5. Add path filters / jobs in `.github/workflows/` if CI needs package-specific
   steps (PHPUnit via wp-env, deploy zip, etc.).
