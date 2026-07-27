# Bundled reference data

## `indonesia-banks.json`

Vendored snapshot of the Indonesian bank catalogue from:

**https://github.com/riobahtiar/data-bank-indonesia** (MIT)

Used for bank / VA dropdowns on payment destinations (transfer codes + names).
No runtime network calls — the file ships with the plugin.

### Refresh

```bash
curl -sL https://raw.githubusercontent.com/riobahtiar/data-bank-indonesia/main/data/banks.json \
  -o data/indonesia-banks.json
```

Or: `npm run data:banks` from this package.
