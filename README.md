# Control Panel Nav

Control Panel Nav is a Craft CMS plugin to help manage your Control Panel navigation.

<img src="https://raw.githubusercontent.com/engram-design/CPNav/master/screenshots/main-new.png" />


## Install

- Add the `cpnav` directory into your `craft/plugins` directory.
- Navigate to Settings -> Plugins and click the "Install" button.
- Click on the settings icon on the right-hand side.


## Functionality

This plugin allows you to rename, reorder, or toggle visibility on menu items for the Control Panel.

You can also create your own menu items to link to either pages inside the control panel, or external links. 

An internal link might be helpful if you have a specific entry you want to easily access. When creating an internal link, you should provide a relative link, rather than including the full URL to your control panel (ie: ~~`http://my.craft.dev/admin/`~~`entries/pages/somepage`).

An external link might be helpful for a variety of different reasons. These should be provided as absolute URLs, complete with protocol (http/https).

You can also set a custom icon for a menu item. This is uploaded to an existing assets source, and is recommended to be an SVG file for best results.

It should also be noted that some plugins already provide a method for changing the Plugin name. While Control Panel Nav is active, it's settings will override any defined in other installed plugins.


## Layouts

**Only available on Craft Client or Craft Pro**

Layouts give you a means to control different navigations on a per-user basis. This can be especially handy if you wish to create a client-specific menu, while not affecting the navigation for site Admins.

To create a layout, click the Layout tab from the Control Panel Nav Settings page. You cannot alter the default layout, which as it suggests, is what users will see if you don't specify a layout.

Craft Pro installs will be able to assign new layouts to user groups, while Craft Client installs will only be able to select the Client account. Craft Personal does not include layout functionality. 


## Roadmap

- Handle menu items with the same name, and handle generation.
- Support for sub-navigation.

Have a suggestion? We'd love to hear about it! [Make a suggestion](https://github.com/engram-design/CPNav/issues)


## Requirements

Control Panel Nav requires a minimum of Craft 2.5 in order to function.


### Changelog

[View JSON Changelog](https://github.com/engram-design/CPNav/blob/master/changelog.json)
