# Upgrade and schema stability

## Pre-1.0 → 1.0

From **1.0.0** onward, the custom table schema is **frozen for additive-only
changes**. That means:

- New columns/tables may appear in a later minor version via `Installer`
  (dbDelta), with a bumped `Installer::DB_VERSION`.
- Existing columns are not renamed or removed without a documented major-version
  migration path.
- Money remains integer minor units; never FLOAT.

### What happens on update

1. On `plugins_loaded`, `Installer::maybe_install()` compares
   `wp_bizwit_db_version` to `Installer::DB_VERSION`.
2. If behind, `dbDelta()` runs every statement in `Schema::statements()`.
3. The option is updated to the new version.
4. Capabilities are re-synced via `Capabilities::maybe_install()`.

Activation hooks are **not** required for updates (in-place ZIP/CLI update).

### Supported upgrade path

| From | To | Notes |
|------|-----|--------|
| 0.3.x – 0.9.x | 1.0.0 | Auto schema upgrade to `1.5.0` tables (includes `bizwit_activity`) |
| Fresh install | 1.0.0 | All tables created at current version |

### Verify after upgrade

```bash
wp eval 'echo get_option( "wp_bizwit_db_version" );'
wp eval 'global $wpdb; var_export( $wpdb->get_col( "SHOW TABLES LIKE \"%bizwit%\"" ) );'
```

Expect `1.5.0` (or higher if a later patch bumped schema) and nine `bizwit_*`
tables including `bizwit_activity`.

### Downgrade

Rolling back the plugin code **below** the schema version that created columns
is unsupported. Data is left in place; missing PHP code paths simply stop
writing new activity rows. Prefer restore-from-backup if you must go back.

### Uninstall

Tables are dropped only when **Delete data on uninstall** is enabled in
Settings. Roles/capabilities are always removed on uninstall.
