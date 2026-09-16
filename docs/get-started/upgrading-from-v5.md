# Upgrading from v5

This guide covers updating Control Panel Nav 5 to Control Panel Nav 6. Test the upgrade on a staging copy and keep a database and Project Config backup before updating the live site.

## Breaking Changes

### Craft CMS Requirements

Control Panel Nav requires Craft CMS 5.9 or later. Check the project’s Craft version before updating the plugin; see [Requirements](/get-started/requirements).

### Custom Icons

Custom icons are selected from a filesystem folder configured by **Icons Path**; see [Configuration](/get-started/configuration#setting-up-custom-icons) for the setting and folder setup. Existing asset-based icons must be selected again after upgrading. The upgrade retains a navigation item’s label, URL, and position, but cannot turn its old asset reference into a relative filesystem path.

Copy the SVG files you want to retain into the folder configured by **Icons Path**. Open each affected item in the navigation builder, select its replacement under **Custom Icon**, and save. For example, an icon stored at `brand/mark.svg` inside that folder is saved using that relative path. Deploy the same folder contents with your project so the icon is available in each environment.

| Control Panel Nav 5 | Control Panel Nav 6 |
| --- | --- |
| Custom icon selected as a Craft asset | Custom icon selected from Icons Path |
| Stored asset reference | Relative SVG path, such as `brand/mark.svg` |

Check the sidebar after selecting the replacement icons, including layouts assigned to different user groups.

### Custom PHP Integrations

If a module or plugin calls Control Panel Nav's navigation services, review it before upgrading. The `Navigations` and `Service` classes, exposed by `getNavigations()` and `getService()`, have been removed. Their navigation-record operations and HTML rendering methods are not available in Control Panel Nav 6.

Search your custom code for `getNavigations()`, `getService()`, `verbb\cpnav\services\Navigations`, and `verbb\cpnav\services\Service`. Also review handlers using `NavigationEvent` or the navigation save/delete events on `Navigations`; these are not emitted by the new customisation service.

For a deliberate reset, the call changes as shown below. These snippets belong in existing custom PHP code with the plugin loaded and `$layoutId` set to the layout you intend to reset. The reset removes manual links, dividers, and other customisations; test with a disposable layout on staging.

::: code-group
```php [Control Panel Nav 5]
\verbb\cpnav\CpNav::$plugin->getService()->resetLayout($layoutId);
```

```php [Control Panel Nav 6]
\verbb\cpnav\CpNav::$plugin->getNavBuilder()->resetLayout($layoutId);
```
:::

In Control Panel Nav 5, reset deletes and recreates saved navigation rows. In Control Panel Nav 6, it clears the layout's customisations and uses the live Craft and plugin menu. Check that the intended layout returns to its default menu and other layouts remain unchanged.

For read operations, `getNavCustomization()->getCustomizationForLayout($layoutUid)` returns saved overrides keyed by node key, not a complete navigation list. An unchanged item may have no saved override. `getNavBuilder()->getLayoutNavItemsForLayout($layoutId)` returns the builder's items for a layout; it is not a permission-filtered menu for an arbitrary user. Do not substitute either method for an old navigation-record query without reviewing how the result is used.

For rendering adjustments and save notifications, use the complete registrations in [Events](/developers/events). Resolved nodes are readonly objects that you replace in the event's array. Single-node saves, removals, and complete-set writes have separate events, so a handler listening only for individual saves will miss reorders and resets. Test every action your integration handles, and verify both its output and the sidebar using an editor account before deployment.

## Behaviour Changes

### Layout Priority for Multiple Groups

Control Panel Nav 5 checks a user's groups in Craft's group order and uses the first matching layout it finds. Control Panel Nav 6 uses the first matching layout in the plugin's Layouts list instead.

If someone belongs to both Editors and Staff, move Editorial above the Staff layout when they should receive Editorial. This needs a review only when people can match multiple layouts. Reorder the Layouts list as needed, then sign in with an affected account and confirm the intended sidebar appears.

## Migrating Navigation Customisations

The upgrade converts saved navigation rows into Project Config customisations. It runs automatically for layouts that have not completed migration and do not already contain the destination customisations. You usually do not need to run the conversion manually.

For a layout that needs investigation or a deliberate rerun, use `cp-nav/migrate-customizations` from the Craft project directory. Start with a preview:

```shell
php craft cp-nav/migrate-customizations --dryRun=1
```

The command prints each layout’s name, UID, number of customisation nodes, and migration status. Review that output before running without `--dryRun`:

```shell
php craft cp-nav/migrate-customizations
```

Use `--layoutUid` to limit the command to one layout. Replace `YOUR_LAYOUT_UID` with the UID shown in the output:

```shell
php craft cp-nav/migrate-customizations --layoutUid=YOUR_LAYOUT_UID --dryRun=1
```

### Preserving Completed Work

Layouts that have completed migration are skipped, including layouts whose navigation has since been intentionally reset. Layouts containing destination customisations are also skipped. This prevents another migration run from replacing subsequent edits.

Use `--force=1` only when you intend to replace a layout’s current customisations with its saved source navigation. Preview the replacement first and keep a backup:

```shell
php craft cp-nav/migrate-customizations --layoutUid=YOUR_LAYOUT_UID --force=1 --dryRun=1
```

Remove `--dryRun=1` to apply that replacement. A layout with no source navigation rows is never cleared, even when using `--force`.

### Reading the Result

| Status | Meaning |
| --- | --- |
| `migrated` | Source navigation was converted. |
| `replaced` | Existing customisations were replaced using `--force`. |
| `skipped_completed` | The layout has already completed migration. |
| `skipped_nonempty` | The layout already contains destination customisations. |
| `skipped_no_legacy` | There are no source navigation rows to convert. |

A dry run reports the proposed result without saving it. After applying the conversion, open each affected layout and check item labels, positions, visibility, manual links, and dividers. Review the resulting Project Config changes before deploying them.

## Checking the Updated Sidebar

Test the sidebar with users from the groups assigned to your layouts. Confirm that custom links open the intended destinations and that hidden items and nested items appear as expected. Reselect the custom icons described above.

The [Audit Customisations command](/developers/console-commands#audit-customisations) can identify stored Craft or plugin keys that no longer match the available navigation sources. Run the audit on staging first and inspect its output before choosing to remove stale customisations.
