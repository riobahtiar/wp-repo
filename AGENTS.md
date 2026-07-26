# WP monorepo — agent guide

**You are in the git root for first-party WordPress work.**

This directory is a normal local `wp-content/` on a WordPress install (Herd +
Dbngin at http://wp.test). Git remote: `git@github.com:riobahtiar/wp-repo.git`
(repo name **wp-repo**).

`CLAUDE.md` in this directory is a **symlink to this file** (same content).

Only packages listed in the root allowlist are versioned; stock themes, uploads,
language packs, and third-party plugins stay on disk but stay out of git.

| | |
|---|---|
| Local site | http://wp.test |
| Server | Laravel Herd (nginx + PHP-FPM) |
| Database | Dbngin (MySQL) |
| PHP | 8.4 (local) · 8.0+ supported by packages |
| Orchestration | npm workspaces + Turborepo |
| JS install | **Root only** — single `package-lock.json` |
| Git root | **This directory (`wp-content/`)** — not WP core, not a single plugin |

---

## Orientation for agents (start here)

### Three-layer doc model

| Layer | Path | Use for |
|-------|------|---------|
| **Monorepo (this file)** | `wp-content/AGENTS.md` | Git, npm workspaces, Turbo, CI, package layout |
| **WP install host** | `../AGENTS.md` | Herd, WP-CLI, security, Indonesia-first product rules, `dbDelta` |
| **Package** | `plugins/<slug>/AGENTS.md` | Domain model, feature conventions, package tooling |

**Precedence:** package → monorepo → install root. Product/domain detail in
`plugins/<slug>/docs/` and `plans/`.

### What lives where

```
…/wp/                         ← WordPress install (NOT the git root)
  AGENTS.md / CLAUDE.md       ← install-wide rules (symlink pair)
  wp-admin/ wp-includes/ …    ← core — never edit, never commit here
  wp-content/                 ← YOU ARE HERE (git root = monorepo)
    package.json              ← workspaces + turbo scripts
    package-lock.json         ← ONLY JS lockfile
    turbo.json
    .github/workflows/        ← only place GitHub Actions run
    AGENTS.md / CLAUDE.md     ← this guide (symlink pair)
    plugins/
      wp-bizwit/              ← first package (part of this repo)
        AGENTS.md / CLAUDE.md
        src/ resources/ docs/ plans/
    themes/ uploads/ …        ← host noise (gitignored except allowlisted packages)
```

### Commands (always from this directory)

```bash
# JS — monorepo root only
npm install
npm ci
npm run build
npm run dev
npm run typecheck
npm run test:unit
npm run lint
npm run phpstan
npm run check                 # typecheck + lint + phpstan + unit + build + budgets

# One package
npm run -w wp-bizwit build
npm run -w wp-bizwit dev
npm run bizwit -- build
turbo run build --filter=wp-bizwit

# PHP — still per package
cd plugins/wp-bizwit && composer install
```

### Hard anti-patterns

```bash
# WRONG: nested lockfile / nested node_modules / broken hoisting
cd plugins/wp-bizwit && npm install

# WRONG: assuming git root is the plugin or the WP install
cd plugins/wp-bizwit && git status   # works only because this is one monorepo tree

# WRONG: adding executable workflows under a package
plugins/wp-bizwit/.github/workflows/*.yml   # GitHub ignores nested workflows
```

If a nested `package-lock.json` appears under a package, **delete it** and run
`npm install` again from this monorepo root.

---

## Golden rules

1. **This repo is not WordPress core.** Never add `wp-admin/`, `wp-includes/`, or
   `wp-config.php`. The parent directory is the WP install; this directory is
   only first-party `wp-content` content.
2. **One package = one plugin or theme.** Put work under
   `plugins/<slug>/` or (later) `themes/<slug>/`. Do not invent a parallel
   `packages/` tree for production code that WordPress must load.
3. **Read the package’s own `AGENTS.md` / `CLAUDE.md` first** for domain work.
   It overrides monorepo defaults for that codebase.
4. **Ship Indonesian (`id_ID`) with the feature.** Translatable but untranslated
   is unfinished for this audience.
5. **Do not commit secrets, `uploads/`, or third-party plugins.** The root
   `.gitignore` allowlists packages; keep it that way when adding a new one.
6. **npm workspaces industry defaults:**
   - Install / `npm ci` **only from the monorepo root**.
   - **One** `package-lock.json` (root). Never commit nested lockfiles.
   - Prefer `npm run -w <package> <script>` over ad-hoc `cd` + `npm run`.
   - PHP: `composer install` still runs **inside** each package.

---

## Packages

| Path | npm name | What it is |
|------|----------|------------|
| [`plugins/wp-bizwit/`](plugins/wp-bizwit) | `wp-bizwit` | Business admin (clients, projects, invoices, payments) for Indonesian companies & UMKM |

Add a new first-party plugin by creating `plugins/<slug>/` with its own
`package.json` (for Turbo), `composer.json` if needed, and an entry in the root
`.gitignore` allowlist (`!/plugins/<slug>/`). Do **not** add a nested
`package-lock.json`.

Package agent entry: `plugins/wp-bizwit/AGENTS.md` (`CLAUDE.md` → same file).

---

## Where docs live

| Layer | Location |
|-------|----------|
| Monorepo (this file + README) | repo root (`wp-content/`) |
| WP install environment | `../AGENTS.md`, `../README.md`, `../SECURITY.md` |
| Package conventions | `plugins/<slug>/AGENTS.md` |
| Package product docs | `plugins/<slug>/docs/` |
| Unbuilt work | `plugins/<slug>/plans/` |

Human-oriented monorepo overview: [`README.md`](README.md).

---

## CI shape

GitHub Actions live in **root** `.github/workflows/` only:

| Workflow | Role |
|----------|------|
| `ci.yml` | `npm ci` → Turbo (JS) + Composer PHPStan/PHPCS + optional wp-env PHPUnit |
| `release-wp-bizwit.yml` | Tag `wp-bizwit/v*` → build, zip, GitHub Release |

Package-local `plugins/*/.github/` may only contain a short pointer README —
never executable workflows. See
[`plugins/wp-bizwit/.github/README.md`](plugins/wp-bizwit/.github/README.md).

### Release tags (multi-package)

```bash
git tag wp-bizwit/v0.3.1
git push origin wp-bizwit/v0.3.1
```

---

## When adding a package

1. Scaffold under `plugins/<slug>/` (or `themes/<slug>/`).
2. Allowlist it in root `.gitignore`.
3. Give it a `package.json` `name` and scripts Turbo should run (`build`,
   `typecheck`, `test:unit`, `lint`, …). **No** nested lockfile.
4. Run `npm install` at the **root** so the root lockfile records the package.
5. Add `AGENTS.md` (+ `CLAUDE.md` → `AGENTS.md` symlink) for agents.
6. Document it in root `README.md` and this file.
7. Extend root CI (path filters / PHP jobs / release workflow) as needed.
