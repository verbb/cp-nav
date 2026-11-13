# Console Commands

Control Panel Nav includes console commands you can run from your Craft project’s directory.

## Customisations

<span id="audit-customisations"></span>

::: reference
### `cp-nav/audit-customizations/index`

Reviews each layout’s saved navigation customisations and reports the ones whose Craft or plugin menu item is no longer available. Run it after changing the menu items available to your project. A customisation can remain stored after its original item is removed, and while the sidebar ignores it, you may want to remove that unused configuration.

| Option | Description | Default |
| --- | --- | --- |
| `--layoutUid` (`-l`) | Limits the audit to the layout with this UID. | All layouts |
| `--fix` | Removes the stale Craft and plugin customisation keys when set to `1`. Manual links and dividers are kept. | `0` |

The command reports each layout’s name and UID, any stale customisation keys, and the number of available menu items without customisations. A UID identifies a layout across environments. Items without customisations use their defaults, so that count doesn’t indicate a problem on its own.

```shell
php craft cp-nav/audit-customizations
```

To inspect one layout, replace `YOUR_LAYOUT_UID` with the UID from the output.

```shell
php craft cp-nav/audit-customizations --layoutUid=YOUR_LAYOUT_UID
```

Before removing stale customisations, run the audit in an environment with the project’s intended plugins and content configuration, such as staging. Review why each listed item is absent, because an item may be temporarily unavailable while a plugin is disabled. Keep a Project Config backup, then remove the stale keys for the layout you reviewed.

```shell
php craft cp-nav/audit-customizations --layoutUid=YOUR_LAYOUT_UID --fix=1
```

The command reports the keys it removed. Run the audit again, check the affected sidebar, and review the Project Config changes before deploying them through your usual process.
:::

<span id="migrate-customisations"></span>

::: reference
### `cp-nav/migrate-customizations/index`

Converts the navigation saved by Control Panel Nav 4 or 5 into Project Config customisations. The upgrade runs this conversion automatically, so you’d only run the command to investigate a layout or deliberately rerun it. Layouts that have already completed migration, or already contain customisations, are skipped unless you use `--force`. See [Migrating Navigation Customisations](/get-started/upgrading-from-v4-or-v5#migrating-navigation-customisations) for how to read its results.

| Option | Description | Default |
| --- | --- | --- |
| `--dryRun` (`-d`) | Reports the result for each layout without saving it when set to `1`. | `0` |
| `--force` (`-f`) | Replaces a layout’s current customisations with its saved source navigation when set to `1`. A layout with no source navigation is never cleared. | `0` |
| `--layoutUid` (`-l`) | Limits the migration to the layout with this UID. | All layouts |

```shell
php craft cp-nav/migrate-customizations --dryRun=1
```

```shell
php craft cp-nav/migrate-customizations --layoutUid=YOUR_LAYOUT_UID --force=1 --dryRun=1
```
:::
