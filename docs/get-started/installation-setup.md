# Installation & Setup
You can install Control Panel Nav via the plugin store, or through Composer.

## Craft Plugin Store
To install **Control Panel Nav**, navigate to the _Plugin Store_ section of your Craft control panel, search for `Control Panel Nav`, and click the _Install_ button.

## Composer
You can also add the package to your project using Composer and the command line.

1. Open your terminal and go to your Craft project:
```shell
cd /path/to/project
```

2. Then tell Composer to require the plugin, and Craft to install it:
```shell
composer require verbb/cp-nav && php craft plugin/install cp-nav
```

## First Steps
Open **Control Panel Nav → Navigation** and choose the layout you want to edit. You can rename, reorder and hide menu items there, then check the result with an editor account assigned to that layout. Hiding a menu item doesn't change anyone's permissions. [Navigation](../feature-tour/navigation.md) explains visibility and ordering, and [Layouts](../feature-tour/layouts.md) covers different menus for user groups.
