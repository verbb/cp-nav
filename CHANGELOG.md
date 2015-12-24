#### 1.6.2

- Minor fix when editing non-manual nav items. URL field is disabled - therefore not passed to controller.

#### 1.6.1

- Added Quick-Add menu button to right-hand utilities area of CP navigation. Can be turned off globally, or restricted via user permissions.

#### 1.6

- Added the option to open manually created menu items in new tab/window. Thanks to [@lindseydiloreto](https://github.com/darylknight) via [#6](https://github.com/engram-design/CPNav/issues/6).
- Automatically redirect to first enabled menu item when accessing a hidden menu item. Thanks to [@darylknight](https://github.com/darylknight) via [#3](https://github.com/engram-design/CPNav/issues/3).

#### 1.5

- Major refactoring codebase and database structure.
- Introducing Layouts to maintain a collection of menu items. Currently a single layout is available.
- New fieldtype.
- Craft Pro sites will allow their users to be able to modify their navigation on a per-user basis (see docs).

#### 1.4

- Performs checks when plugins are installed/uninstalled or enabled/disabled to determine if menu item should be removed or added.
- Performs checks when Craft adds a new page (Globals, Categories, Assets, etc) to add/remove from the navigation.

#### 1.3

- Added ability to delete user-created menu items. You cannot delete a core menu item, or a plugin menu item. You can hide these instead.
- When updating a menu item, it will instantly reflect changed in the CP's navigation without refreshing the page.

#### 1.2

- Updated interface.
- Added option to restore the default navigation, which removes all your settings to easily start again.
- Added support to create new menu item. Handy for external links, or direct page navigation.
- Added support to edit menu item url.

#### 1.1

- Fix for lack of full Url support - especially for Globals area.

#### 1.0

- Initial release.