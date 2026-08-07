# Node Keys

Every sidebar item is identified by a stable **node key**. Customizations, merge, cache, and the builder all reference keys — not mutable labels or URLs alone.

## Format

```
{namespace}:{path…}
```

| Namespace | Pattern | Example |
| --- | --- | --- |
| `craft` | `craft:{url}` | `craft:dashboard`, `craft:content/entries` |
| `craft` (subnav) | `craft:{parentUrl}/{subHandle}` | `craft:graphql/schemas` |
| `plugin` | `plugin:{handle}` | `plugin:formie` |
| `plugin` (subnav) | `plugin:{handle}:{subHandle}` | `plugin:formie:submissions` |
| `manual` | `manual:{uuid}` | `manual:f47ac10b-58cc-4372-a567-0e02b2c3d479` |
| `divider` | `divider:{uuid}` | `divider:6ba7b810-9dad-11d1-80b4-00c04fd430c8` |

## Project config encoding

Keys contain `:` which is awkward in project config paths. Stored path segments encode the key (e.g. `craft__dashboard`) while the stored value keeps the canonical `key` field.

## Rules

- Craft/plugin keys are derived from the live nav sources at build time.
- Manual and divider keys are UUIDs assigned when the item is created.
- Overlay entries for removed Craft/plugin keys are ignored at resolve (no project config write on read); clean them with `cp-nav/audit-customizations --fix`.
