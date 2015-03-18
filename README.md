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

An internal link might be helpful if you have a specific entry you want to easily access, or any other sort of page you want to add. When creating an internal link, you should provide a relative link, rather than including the full URL to your control panel (ie: ~~`http://my.craft.dev/admin/`~~`entries/pages/somepage`).

An external link might be helpful for a variety of different reasons. These should be provided as absolute URLs, complete with protocol (http/https).

It should also be noted that some plugins already provide a method for changing the Plugin name. While Control Panel Nav is active, it's settings will override any defined in other installed plugins.


## Layouts

Layouts allow you to tailor the CP navigation on a per-user basis. Essential for having one menu for Admin accounts, while others for your Client, Members, etc.

Because Craft allows Users to be assigned to more than a single User Group, we cannot effectively define layouts based on User Groups. Instead we can define Layouts, and we selectively choose what users are assigned to a layout.

A typical setup will have the following layouts as a guide:

- Default (what regular members will see)
- Client (for your client, or lower-admin)
- Admin (what you'll see)

You'll always have a `Default` Layout, which you should use to define what new users will encounter. This way, you won't have to assign a user to a layout whenever a new user is created with CP access.

You can create as many layouts as you need, and assign users to these layouts. Simply click on the Layout tab.


## Roadmap

- Create menu configurations for user groups.
- Support import/export (although maybe not useful).

Have a suggestion? We'd love to hear about it! [Make a suggestion](https://github.com/engram-design/CPNav/issues)

<!---
## Troubleshooting

While every effort is made to ensure this plugin is error free, if an error can happen, it likely will. Murphy's law and all that. When it does, we want you to know exactly how to fix it.

Because of the way the `modifyCpNav()` hook works, this plugin's code will fire on every single page of the CP. This means that if something were to go wrong, access to the CP would be lost - blocking you from even uninstalling the plugin.

To manually uninstall the plugin, you can do one of the following:

- Remove the `cpnav` directory from the `craft/plugins` directory
- Remove necesarry tables and data from the MySQL database.

To remove MySQL data, locate the `craft_cpnav` table and delete (drop) it. Next, find the `craft_plugins` table, find the row that contains `CpNav` for the `class` column value and delete it. After this, please immediately [submit an issue](https://github.com/engram-design/CPNav/issues).

If the above scares you into thinking this plugin is dangerous - there's no need to. Control Panel Nav doesn't effect and other database tables other than its own. It's even used on several live sites right now. We simply want to provide the above instructions so you're well informed.
-->

### Changelog

#### 1.5

- Major refactoring codebase and database structure.
- Introducing Layouts to maintain a collection of menu items for...
- User support. You can assign users to use a layout, along with have a default layout that will be used.

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