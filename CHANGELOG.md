# Changelog

## Unreleased

### Added
- Plugin setting **Icons Path** for portable custom SVG icons (relative paths in project config; ImageBrowser picker in the builder).
- Manual URL scheme allowlist: relative paths plus `http`/`https`/`mailto`/`tel`.

### Changed
- Normalize CP General Settings to the shared `verbb-base` settings layout (`pageTabs` / `pageTitle` / `pageAction` helpers; Settings → Plugins → Control Panel Nav crumbs).
- Raised the Craft CMS requirement to **5.9+** (matches nav sources/`ElementSources::getPages()`).
- `cp-nav/migrate-customizations` skips nonempty v6 layouts by default; use `--force` to replace. Layouts with no legacy rows are never cleared.
- Nav sources cache uses a generation key for shared eviction and includes CP language in the fingerprint.
- Project-config node path segments use collision-free base64url encoding (legacy underscore paths still read; saves rewrite to the new form).
- Builder reorder/indent payloads use canonical `key` / `parentKey` (CRC32 `builderId` is display-only).
- Custom icons no longer use Craft assets — configure a filesystem Icons Path instead.
- Frontend Plugin Kit deps back on npm (`@verbb/plugin-kit-*` ^2.0.16); dropped local `file:` checkouts.

### Fixed
- CP sidebar divider styles now key off the server-rendered `nav-divider-*` id so labels no longer flash as normal nav items before sidebar JS runs.
- Renaming or editing layout metadata no longer wipes nested navigation customizations.
- Editing a manual link URL now persists the new URL (previously reported success while keeping the old value).
- First toggle/reorder of a Craft/plugin item no longer snapshots resolved URL/icon into the overlay.
- Outdenting a native Craft/plugin child to the root now survives project-config round-trips (`parent: ""` sentinel vs inherit).
- v5 → v6 upgrade keeps manual/divider labels when `currLabel === prevLabel`, maps craft subnav with parent context (e.g. GraphiQL), and does not undo intentional root outdents via `prevParentId`.
- Nav source capture now runs after other `EVENT_REGISTER_CP_NAV_ITEMS` handlers so project/plugin additions are included.
- Refresh sources forces a rebuild after cache invalidation.
- Non-admin CP users can no longer select another layout via the `layoutId` query param.
- Plugin visibility respects `getCpNavItem() === null`; Settings remains visible to admins when `allowAdminChanges` is false.
- Subnav handles prefer registry provider handles; manual siblings with the same URL basename no longer overwrite each other.
- Builder reorder indexes the layout once per operation; Show toggles ignore stale out-of-order responses.

## 6.0.0-beta.2 - 2026-08-07

### Added
- Added **Duplicate** control on the Layouts tab (copies permissions + customizations). (#144)

### Changed
- Softened CP sidebar **divider** styles for Craft 5 — line–label–line with muted hairline/text tokens instead of the solid dark label badge from Craft 3/4.
- Layout selection for multi-group users now prefers layout table order (`sortOrder`) over Craft’s user-group order. (#150)
- Manual URLs expand Craft env variables / aliases at render (`App::parseEnv`), then `{site}` / `{siteHandle}` tokens. (#128)

### Fixed
- New Craft/plugin nav items now insert between their Craft default neighbours when the layout overlay still has frozen sorts from before those items existed (e.g. Entries after adding a section no longer lands after Assets on a key tie-break).

## 6.0.0-beta.1 - 2026-08-07

### Added
- Added React control panel **nav builder** replacing the legacy Garnish flat-table UI.
- Added **nav sources** pipeline (`NavSources` / `NavSourceBuilder`) that captures Craft’s live CP nav (`Cp::nav()` / `Cp::EVENT_REGISTER_CP_NAV_ITEMS`) with fingerprint caching.
- Added **nav customizations** overlay in project config (`customizations.nodes`) — only admin overrides are persisted (order, visibility, labels, icons, manual items, dividers).
- Added stable **node keys** (`craft:`, `plugin:`, `manual:`, `divider:`) with encoded project config path segments.
- Added resolve + render stack (`NavResolver`, `NavRenderer`, `NavPermissions`) injecting the resolved tree via Craft’s nav registration event.
- Added builder APIs for create/update/delete/reorder/indent/outdent/reparent, layout reset, and sources refresh.
- Added dismissible **new items** notice when registry keys appear that aren’t yet acknowledged.
- Added custom SVG icon support via asset upload (local filesystem path or remote asset contents).
- Added `{site}` / `{siteHandle}` token substitution for manual URLs at render.
- Added console commands `cp-nav/migrate-customizations` and `cp-nav/audit-customizations` (with `--fix`).
- Added public events `NavResolver::EVENT_MODIFY_RESOLVED_NAV` and `NavCustomization::EVENT_AFTER_SAVE_NODE` / `AFTER_REMOVE_NODE` / `AFTER_SET_NODES`.
- Added Pest integration test harness covering sources parity, merge/default-position insert, read-only PC safety, builder APIs, and edge cases.

### Changed
- Frontend assets reorganised under `src/web/assets/{builder,settings,sidebar}` — each owns its `dist/`; builder uses `nystudio107/craft-plugin-vite` + manifest.
- Control panel page views **never write project config** — sync-on-read / `_checkUpdatedNavItems` behaviour is gone.
- New Craft/plugin nav items insert at their **default Craft position** among siblings (not append-to-end).
- Reset layout clears the customization overlay for that layout (returns to live Craft/plugin order) instead of recreating snapshot rows.
- Project config shape moves layout nav data under `cp-nav.layouts.{uid}.customizations.nodes.{encodedKey}`.
- Layout priority for users in multiple groups is the first matching layout by ascending `sortOrder`.
- Nesting depth is capped at **two levels** (Craft sidebar alignment).
- Plugin settings tab removed — reset lives on the builder header for the selected layout; plugin gear still opens the builder.
- Craft Icon picker removed from the node editor; override icons only via **Custom Icon** (SVG asset). Registry/migrated `icon` values still render.

### Fixed
- Fixed custom SVG icons on Craft 5 filesystems (local root path + remote `Asset::getContents()` fallback).
- Fixed GraphiQL / external Craft items preserving `external` through resolve → render.
- Fixed orphan `manual:*` / `divider:*` nodes under removed parents reparenting to top level.

### Removed
- Removed the `cpnav_navigation` database table (archived as `cpnav_navigation_v5_archive` on upgrade).
- Removed legacy project config key `cp-nav.navigations`.
- Removed `prev*` dual-state fields and full nav snapshot persistence.
- Removed Garnish HUD / flat-table navigation editor.
- Removed DOM MutationObserver / owned Twig nav swap as the primary render path (Craft event injection is canonical).

## 5.0.7 - 2026-03-15

### Fixed
- Fix compatibility with Craft 5.9+ and new layout changes.

## 5.0.6 - 2026-03-03

### Fixed
- Fix compatibility with Craft 5.9+ and new layout changes.

## 5.0.5 - 2025-07-18

### Fixed
- Fix an error when applying Layout changes.

## 5.0.4 - 2025-03-05

### Fixed
- Fix external link icon style.
- Fix custom icons not rendering correctly.

## 5.0.3 - 2025-02-05

### Fixed
- Update Craft icon collection.
- Tweak color contrast for divider labels.
- Fix nav icon showing for divider nodes.
- Fix expanded subnav state not being respected.
- Fix support for GraphQL on Craft Solo.

## 5.0.2 - 2024-09-07

### Fixed
- Fix an error when updating nav items.
- Fix icon not working for nav item, when set to “First Letter”.

## 5.0.1 - 2024-06-24

### Added
- Add Craft Teams support for permissions.

### Changed
- Update English translations.

### Fixed
- Fix an error when saving or creating layouts.

## 5.0.0 - 2024-05-20

### Changed
- Now requires PHP `8.2.0+`.
- Now requires Craft `5.0.0+`.
- Update compatibility with Craft 5 menu changes.

### Removed
- Remove `subnavBehaviour` in favour of native Craft 5 menu updates.

## 4.0.13 - 2025-07-18

### Changed
- Update English translations.

## 4.0.12 - 2024-03-04

### Fixed
- Fix order of operations when uninstalling the plugin.

## 4.0.11 - 2023-10-25

### Fixed
- Fix an error when migrating from Craft 3.
- Fix an error for some plugins when trying to determine any subnav items.

## 4.0.10 - 2023-01-18

### Changed
- Only admins are now allowed to access plugin settings.

### Fixed
- Fix an error when checking for an authenticated users too early before Craft (and other plugins) are ready.
- Fix icons not rendering correctly on Windows.

## 4.0.9 - 2022-11-13

### Fixed
- Fix multiple nested subnav items not appearing correctly.

## 4.0.8 - 2022-11-08

### Fixed
- Fix subnav items not showing correctly.

## 4.0.7 - 2022-10-27

### Added
- Add support for plugins that use `EVENT_REGISTER_CP_NAV_ITEMS` to register nav items.

### Fixed
- Fix top-level plugins with no subnavs being unable to be nested within another nav item.
- Fix multi-level navigation items expanding when re-ordered.
- Fix toggle/move indicator alignment for settings.
- Fix navigation items being duplicated in some instances.
- Fix an error when trying to set a custom icon on a navigation item.

### Removed
- Removed the ability to collapse/expand subnav items in the navigation builder, to fix a few tricky edge cases that are too tricky to deal with!

## 4.0.6 - 2022-08-23

### Fixed
- Fix an issue with subnav items getting saved before their parent navigation item.
- Fix an error when saving navigations and their layout was yet to exist.
- Fix lack of permissions checks for plugin subnav items.
- Fix Craft 3 migration for some plugins.

## 4.0.5 - 2022-07-29

### Fixed
- Fix an error when updating from Craft 3.

## 4.0.4 - 2022-07-22

### Added
- Add english translations.

### Fixed
- Fix migration from Craft 3 not generating subnavs.
- Fix an error when rebuilding project config.
- Fix an error when uninstalling.

## 4.0.3 - 2022-07-05

### Fixed
- Fix an error caused by Craft 4.1 removing font data.
- Add checks around JS `waitForElm` function.

## 4.0.2 - 2022-06-08

### Fixed
- Fix an error when upgrading from Craft 3, when also having for some plugins (SEOmatic) installed.

## 4.0.1 - 2022-05-26

### Fixed
- Fix an error when trying to fetch a layout for a user, when no user session was detected.

## 4.0.0 - 2022-05-05

### Added
- Added the ability to create and manage subnav items.
- Added "Craft" and "Plugin" navigation types, to have more fine-grained control over certain navigation items.
- Added the ability to change the subnav behaviour. Either open only when the parent is active (Craft default), always open, or toggle-state open.
- Added plugin setting to control default subnav behaviour for all navigations.
- Minor UI polish inline with Craft 4 (spinners, colours, shadows, etc).
- Added better support for Craft font-icons, which now parses their icon font files for available glyphs.
- Added `project-config/rebuild` support.
- Added `archiveTableIfExists()` to install migration.

### Changed
- Now requires PHP `8.0.2+`.
- Now requires Craft `4.0.0+`.
- Improved performance when rendering navigation items.
- Revamped UI for navigations to allow for nested items.
- Plugin and Craft subpages can now be managed in the navigation builder.
- The rendered HTML navigation is now completely custom, giving us more control over output, allowances for customisation and more.
- Divider navigation items are now customisable.
- Divider navigation items now no longer require a label, and are rendered as a line.
- Navigation items without a URL now render as a heading.
- Navigation items with "New window" enabled now add `rel="noopener"` to the anchor tag.
- Now requires CP Nav `3.0.17` in order to update from Craft 3.
- Renamed service classes.
- Renamed base plugin methods.

### Fixed
- Fixed an issue where in some cases, menu items were still being shown in the user didn't have permission to access it.
- Fixed navigation items getting out of sync for different users.
- Fixed issues comparing the normal Craft-generated navigation with your custom one.
- Fixed an issue where project config wouldn't be kept up to date correctly for navigation items.
- Fixed default layout not always being created on-install.
- Fixed menu items to expand if two similarly-named pages exist and one is active.
- Fixed an error where some new navigation items weren’t created if they had similar handles to other items.
- Removed leftover debug badges.

### Removed
- Removed `cpnav_pending_navigations` database table.

## 3.0.17 - 2021-06-30

### Fixed
- Fix Plugin Store link being hidden for `allowAdminChanges = false`.
- Fix potential error when migrating nav items.

## 3.0.16 - 2021-05-30

### Fixed
- Fix being unable to remove dividers.
- Fix divider’s not always displaying correctly.
- Fix selected state not being set for Globals.

## 3.0.15 - 2021-02-03

### Added
- Allow CpNav plugin services to be used in console requests. (thanks @jamesmacwhite).

### Fixed
- Fix an error with PHP 7.2+ in some cases.

## 3.0.14 - 2021-01-22

### Fixed
- Fixed potential error for layouts without a `sortOrder` setting.

## 3.0.13.1 - 2020-08-12

### Fixed
- Fixed potential issue with migration.

## 3.0.13 - 2020-08-12

### Added
- Layouts can now be sorted in the control panel.

### Fixed
- Fix project config storing layoutId instead of layoutUid.
- Fix layout and navigation items getting out of sync with project config.

## 3.0.12 - 2020-04-28

### Fixed
- Fix custom icon not working when editing items.

## 3.0.11 - 2020-04-21

### Fixed
- Fix directory separator checks on Windows, causing icon issues.
- Fix over-zealous updates to `originalNavHash`.

## 3.0.10 - 2020-04-16

### Fixed
- Fix logging error `Call to undefined method setFileLogging()`.

## 3.0.9 - 2020-04-15

### Changed
- File logging now checks if the overall Craft app uses file logging.
- Log files now only include `GET` and `POST` additional variables.
- Ensure plugin project config is removed when uninstalling.

### Fixed
- Add checks to prevent multiple nav items being added in some instances.

## 3.0.8.2 - 2020-04-01

### Fixed
- Fix permissions migration causing errors for user group permissions.

## 3.0.8.1 - 2020-03-16

### Fixed
- Fix migration introduced in 3.0.8.

## 3.0.8 - 2020-03-16

### Fixed
- Add better support for multi-users. Previously, nav's could get confused with multiple CP-enabled users logging in, and being able to run change-detection.
- Add error-handling to `checkUpdatedNavItems` to prevent fatal errors.

## 3.0.7 - 2020-02-28

### Fixed
- Fix nav re-generating in some circumstances, when it didn't need to.

## 3.0.6 - 2020-02-25

### Fixed
- Fix error thrown on saving a navigation in some instances.
- Fix triggering project config changes when `allowAdminChanges` is set to false.

## 3.0.5 - 2020-02-24

### Changed
- Add better handling and detection of changed nav, rather than just plugin install/uninstall. For instance, when adding your first section or global.

### Fixed
- Fix some icons (including GraphQL) not showing correctly.
- Fix missing order value when resetting nav.

## 3.0.4 - 2020-02-15

### Fixed
- Fix migration errors from Craft 2 > Craft 3.

## 3.0.3 - 2020-02-12

### Changed
- Update icons and sort alphabetically by label. (thanks @dennisfrank).

### Fixed
- Fix situations where saving a node throws an error because its layout doesn’t exist.

## 3.0.2 - 2020-02-05

### Fixed
- Fix error in `NavigationEvent`.

## 3.0.1 - 2020-02-02

### Fixed
- Fix error on brand-new installs, where the default layout wasn’t being created.

## 3.0.0 - 2020-01-14

### Added
- Add better support for user permissions. Previously, even if a user didn't have access to a navigation item, it'd still be shown. Now respects Craft's native user permissions.
- Nav URLs can now contains aliases.
- Add navigation types, for better flexibility going forward.
- Add new divider navigation type, handy for separating items in the menu.
- You can now select an icon from Craft's built-in icons.
- Add Project Config support.

### Changed
- Now requires Craft 3.4.x and greater.
- Full refactor from ground-up, focusing on performance, cleanup and best-practices.
- Minimised overhead for CP requests, by removing changed navigation checks.
- Modified navigation items are only checked when enabling/installing or disabling/uninstalling plugins.
- Nav URL is now optional, and also acts like a heading.
- Nav regeneration has been changed to nav resetting. This will completely reset your layout back to stock Craft.

### Fixed
- Fix empty navigation handle issue. (thanks @fThues).
- Layout user group IDs now correctly use their UIDs.
- Fix error for non-local volumes when using a custom icon.

## 2.0.9 - 2018-11-10

### Added
- Added better visual notification when editing a nav.

### Fixed
- Fix custom icons not resolving with aliases.
- Fix errors when installing via console.
- Fix redirection issue for settings.

## 2.0.8 - 2018-07-05

### Added
- Add nav regenerate settings

### Fixed
- Fix default icons not appearing (showing as placeholders)
- Fix missing template translations

## 2.0.7 - 2018-05-25

### Fixed
- Fix Craft native menu item icons

## 2.0.6 - 2018-05-10

### Fixed
- Fix hardcoded plugin icons

## 2.0.5 - 2018-05-08

### Fixed
- Fix for count() error in PHP 7.2+
- Badge support
- Fix incorrect column default values for plugin
- Fix subnav items not working correctly for some plugins

## 2.0.4 - 2018-04-11

### Fixed
- Fix for Craft 3.0 (changing Client to Solo)
- Fix not including sub-navigation of plugins
- Fix not including `sel` class on menu items
- Fix icon path check to ignore directories

## 2.0.3 - 2018-02-12

### Fixed
- Fix for siteUrl config settings throwing an error when more than one site is defined
- Update Craft CMS requirements

## 2.0.2 - 2017-12-13

### Fixed
- Fixed issue when using arrays in configuration files. Thanks to [@juresrpcic](https://github.com/juresrpcic) via [#30](https://github.com/verbb/cp-nav/issues/30).
- Fixed issue with volumes sources when adding a custom icon.

## 2.0.1 - 2017-12-07

### Changed
- Updated for Craft 3 RC1.

## 2.0.0 - 2017-10-18

### Added
- Craft 3 initial release.

## 1.7.8 - 2017-10-17

### Added
- Verbb marketing (new plugin icon, readme, etc).

### Changed
- Don’t store handle as an `AttributeType::Handle`.
- Better error-handling when adding/saving nav item.

## 1.7.7 - 2017-04-21

### Added
- Support for Craft 2.6.2951.

### Changed
- Now restores original nav if errors are thrown during the nav generation process (PHP7+ only).
- Enforce only image selection for custom icon.
- Allow custom icons to override default Craft/Plugin icons.

### Fixed
- Fixed selected custom icon not populating asset element select field.
- Check if a custom icon asset exists before trying to apply it to the nav.

## 1.7.6 - 2016-11-01

### Fixed
- Fixed issues when saving nav items in some cases.
- Fixed issue where Permissions option for layouts was required when saving/editing.
- Fixed minor UI issues with HUD when editing nav and layout items.

## 1.7.5 - 2016-06-25

### Added
- Added support for translations with menu labels.
- Added ability to upload and set custom icons for menu items.

## 1.7.4 - 2016-02-28

### Fixed
- Fixed issue with `{siteUrl}` being an array not a string. Thanks to [@slelorrain](https://github.com/slelorrain).

## 1.7.3 - 2016-02-20

### Added
- Added `{siteUrl}` twig tag when creating nav items.

## 1.7.2 - 2016-02-02

### Fixed
- Fixed issue with url's not being properly sanitised and processed.

## 1.7.1 - 2016-01-13

### Fixed
- Fixed issue with plugin release feed url.

## 1.7.0 - 2015-12-23

### Added
- Craft 2.5 support, including release feed and icons.
- Added Layouts - set different navigations and assign to user groups. Great for creating client-specific navigation, without changing navigation for other users.

### Changed
- Completely re-written from the ground up for better performance and tidiness.
- Removed Quick-Add menu for the moment.

## 1.6.2 - 2015-12-23

- Minor fix when editing non-manual nav items. URL field is disabled - therefore not passed to controller.

## 1.6.1 - 2015-12-23

- Added Quick-Add menu button to right-hand utilities area of CP navigation. Can be turned off globally, or restricted via user permissions.

## 1.6 - 2015-12-23

- Added the option to open manually created menu items in new tab/window. Thanks to [@lindseydiloreto](https://github.com/darylknight) via [#6](https://github.com/verbb/cp-nav/issues/6).
- Automatically redirect to first enabled menu item when accessing a hidden menu item. Thanks to [@darylknight](https://github.com/darylknight) via [#3](https://github.com/verbb/cp-nav/issues/3).

## 1.5 - 2015-12-23

- Major refactoring codebase and database structure.
- Introducing Layouts to maintain a collection of menu items. Currently a single layout is available.
- New fieldtype.
- Craft Pro sites will allow their users to be able to modify their navigation on a per-user basis (see docs).

## 1.4 - 2015-12-23

- Performs checks when plugins are installed/uninstalled or enabled/disabled to determine if menu item should be removed or added.
- Performs checks when Craft adds a new page (Globals, Categories, Assets, etc) to add/remove from the navigation.

## 1.3 - 2015-12-23

- Added ability to delete user-created menu items. You cannot delete a core menu item, or a plugin menu item. You can hide these instead.
- When updating a menu item, it will instantly reflect changed in the CP's navigation without refreshing the page.

## 1.2 - 2015-12-23

- Updated interface.
- Added option to restore the default navigation, which removes all your settings to easily start again.
- Added support to create new menu item. Handy for external links, or direct page navigation.
- Added support to edit menu item url.

## 1.1 - 2015-12-23

- Fix for lack of full Url support - especially for Globals area.

## 1.0 - 2015-12-23

- Initial release.
