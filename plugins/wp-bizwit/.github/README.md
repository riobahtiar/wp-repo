# CI for this package lives at the monorepo root

This plugin is part of **wp-repo**. GitHub Actions only load workflows from the
**repository root** (`.github/workflows/`). Nested workflow YAML under packages
is not executed and must not be re-added.

| Job | Root workflow |
|-----|----------------|
| Build, typecheck, unit tests, PHPCS, PHPStan, PHPUnit | [`.github/workflows/ci.yml`](../../../.github/workflows/ci.yml) |
| Tagged release zip | [`.github/workflows/release-wp-bizwit.yml`](../../../.github/workflows/release-wp-bizwit.yml) |

## Release tags (multi-package convention)

Prefix tags with the package name so one monorepo can ship several plugins:

```bash
# From monorepo root
git tag wp-bizwit/v0.3.1
git push origin wp-bizwit/v0.3.1
```

## Local commands (always from monorepo root)

```bash
npm install
npm run -w wp-bizwit build
npm run -w wp-bizwit test:unit
cd plugins/wp-bizwit && composer install
```
