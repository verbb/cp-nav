# Changelog

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
