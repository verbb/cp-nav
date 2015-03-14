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

You can also create your own menu items to link to either pages inside the control panel, or external links. This might be helpful if you have a specific entry for your client's to easily access, or any other sort of page you want to add. If creating an internal link to pages in the control panel, you should be provide a relative link, rather than the full URL to your control panel (ie: `http://my.craft.dev/admin/**entries/pages/somepage**`).

An external link might be helpful if you offer support via a third-party website, or through your agencies own website. These should be provided as absolute URLs, complete with protocal (http/https). Or maybe you want people to Google something first before asking for support.


## Roadmap

- Cleanup stale menu items when removing other plugins.
- Create menu configurations for user groups.
- Support live-refreshing the CP navigation whenever a change is made.
- Support deleting user-added menu items.
- Support import/export (although maybe not useful).

Have a suggestion? We'd love to hear about it! [Make a suggestion](https://github.com/engram-design/CPNav/issues)


### Changelog

#### 1.2

- Updated interface.
- Added option to restore the default navigation, which removes all your settings to easily start again.
- Added support to create new menu item. Handy for external links, or direct page navigation.
- Added support to edit menu item url.

#### 1.1

- Fix for lack of full Url support - especially for Globals area.

#### 1.0

- Initial release.