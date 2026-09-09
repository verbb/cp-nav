# Console Commands

## Migrate Customizations

Convert archived or live v5 navigation rows into v6 project config customizations.

```shell
php craft cp-nav/migrate-customizations [--dry-run] [--force] [--layoutUid=UID]
```

| Option | Description |
| --- | --- |
| `--dry-run` | Report what would be written without saving |
| `--force` | Replace layouts that already have v6 customizations (destructive) |
| `--layoutUid` | Limit to a single layout UID |

On plugin upgrade this runs automatically for layouts that do not already have v6 customizations.

Safe by default: layouts with existing customizations are **skipped** unless `--force` is passed. Layouts with no legacy v5 rows are skipped (never cleared). Status per layout is printed as `migrated`, `replaced`, `skipped_nonempty`, or `skipped_no_legacy`.

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
