# Control Panel Nav

Control Panel Nav is a Craft CMS plugin to help manage your Control Panel navigation.

**Please note:** this plugin requires a minimum of Craft 2.3.2640.

<img src="https://raw.githubusercontent.com/engram-design/CPNav/master/screenshots/main-new.png" />


## Install

- Add the `cpnav` directory into your `craft/plugins` directory.
- Navigate to Settings -> Plugins and click the "Install" button.
- Click on the `Control Panel Nav` link on the left-hand side (we figure you don't need another menu item to manage).


## Functionality

This plugin allows you to rename, reorder, or toggle visibility on menu items for the Control Panel.

You can also create your own menu items to link to either pages inside the control panel, or external links. 

An internal link might be helpful if you have a specific entry you want to easily access. When creating an internal link, you should provide a relative link, rather than including the full URL to your control panel (ie: ~~`http://my.craft.dev/admin/`~~`entries/pages/somepage`).

An external link might be helpful for a variety of different reasons. These should be provided as absolute URLs, complete with protocol (http/https). Opening these in a new window/tab is not currently supported.

It should also be noted that some plugins already provide a method for changing the Plugin name. While Control Panel Nav is active, it's settings will override any defined in other installed plugins.


## Quick-Add menu

In the Settings tab, you'll find the option to 'Show Quick-Add Menu'. If switched on, a small '+' button will appear on the right-hand side of the CP, next to the Settings icon. Clicking this will show a tooltip where you can enter details for a new menu item.

This button can be turned off globally, or restricted to certain users through permissions.

A button also provides a shortcut to the main settings page.

<img src="https://raw.githubusercontent.com/engram-design/CPNav/master/screenshots/quick-add.png" width="250" />


## Roadmap

- Better support for user-allocation of layouts (especially for Craft Client/Personal).
- Handle menu items with the same name, and handle generation.

Have a suggestion? We'd love to hear about it! [Make a suggestion](https://github.com/engram-design/CPNav/issues)


### Changelog

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