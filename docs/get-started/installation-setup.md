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

## Arrange the Sidebar

Open **Control Panel Nav → Navigation** and select the layout you want to edit. Rename Entries to Articles, save the label and drag the row to its intended position. Reload the control panel and check that Articles still opens the Entries screen.

Test with an editor account assigned to that layout too. Hiding or showing a menu item does not change the user's permissions. [Navigation](docs:feature-tour/navigation) explains visibility and ordering, while [Layouts](docs:feature-tour/layouts) covers different menus for user groups.
