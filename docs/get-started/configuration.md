# Configuration

You can customise Control Panel Nav’s settings using a PHP configuration file. This is optional: each setting has a default, so you only need to include the values you want to change.

To override a setting, create `cp-nav.php` in your Craft project’s `/config` directory and return an array of setting names and values. For example, the following will use a custom icon directory:

```php
<?php

return [
    'iconsPath' => '@webroot/custom-icons/',
];
```

All other settings keep their defaults. Add any further settings you want to change to the same array. The options below explain the available settings and their defaults.

## Configuration Options


::: reference
### `iconsPath`

**Type:** `string` · **Default:** `'@webroot/cpnav-icons/'`

The filesystem folder containing your custom SVG icons. The default is `@webroot/cpnav-icons/`. Craft aliases and environment variables are supported.
:::


## Control Panel

Open **Settings → Plugins → Control Panel Nav** as an administrator, then select the **Settings** tab. Under **General Settings**, enter your folder in **Icons Path** and save. A value defined in `config/cp-nav.php` takes precedence over the control-panel setting.

## Setting Up Custom Icons

Suppose you want a branded icon for your Team Handbook link. With the default **Icons Path**, create a `cpnav-icons` folder inside your project's web root, the folder Craft's `@webroot` alias points to. Add your SVG there, for example at `cpnav-icons/brand/mark.svg`. Icons can be in the folder itself or one subfolder, as in this example. Use filenames containing letters, numbers, dots, underscores, or hyphens. The web server must be able to read the folder and file.

Open **Control Panel Nav → Navigation**, choose the intended layout, and edit the Team Handbook item. Select `brand/mark.svg` under **Custom Icon** and click **Save**. Reload the control panel to check the icon beside the link.

The item stores `brand/mark.svg` relative to **Icons Path**. Include the icon folder in your deployment so the same relative file exists in every environment. If each environment uses a different absolute folder, configure **Icons Path** for each environment while keeping the folder contents consistent.

If the picker contains no icons, check that **Icons Path** resolves to the folder containing your SVG files and that the web server can read them. If an icon is missing after deployment, check the saved relative path against the deployed folder, then reload the control panel.
