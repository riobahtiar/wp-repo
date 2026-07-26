# Workflows moved to monorepo root

This plugin lives in the **wp-repo** monorepo. GitHub only runs workflows from
the repository root:

| Former file (here) | Monorepo replacement |
|--------------------|----------------------|
| `workflows/test-analyse.yml` | [`.github/workflows/ci.yml`](../../../.github/workflows/ci.yml) |
| `workflows/setup.yml` | Inlined into root CI / release jobs |
| `workflows/deploy.yml` | [`.github/workflows/release-wp-bizwit.yml`](../../../.github/workflows/release-wp-bizwit.yml) |

**Release tags** use a package prefix so multiple plugins can ship from one repo:

```bash
git tag wp-bizwit/v0.3.0
git push origin wp-bizwit/v0.3.0
```

The YAML files in `workflows/` are kept only as historical reference and are
not executed.
