# Console Commands

## Migrate Customizations

Convert archived or live v5 navigation rows into v6 project config customizations.

```shell
php craft cp-nav/migrate-customizations [--dry-run] [--layoutUid=UID]
```

| Option | Description |
| --- | --- |
| `--dry-run` | Report what would be written without saving |
| `--layoutUid` | Limit to a single layout UID |

On plugin upgrade this runs automatically for layouts that do not already have v6 customizations. Re-running is safe (idempotent for empty-overlay layouts).

## Audit Customizations

Compare stored customization keys against the live nav sources.

```shell
php craft cp-nav/audit-customizations [--layoutUid=UID] [--fix]
```

| Option | Description |
| --- | --- |
| `--layoutUid` | Limit to a single layout UID |
| `--fix` | Remove stale Craft/plugin keys that are no longer in sources (`manual:*` / `divider:*` are kept) |

Prefer running audit (and optional `--fix`) on staging, then deploying project config — not as a surprise write on production page views.
