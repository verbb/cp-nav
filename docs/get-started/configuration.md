# Configuration

You can customise Control Panel Nav’s settings using a PHP configuration file. This is optional: each setting has a default, so you only need to include the values you want to change.

To override a setting, create `cp-nav.php` in your Craft project’s `/config` directory and return an array of setting names and values. For example, the following will use a custom icon directory:

```php
<?php

return [
    'iconsPath' => '@webroot/custom-icons/',
];
```

All other settings keep their defaults. Add any further settings you want to change to the same array. The options below explain the available settings and their defaults. You can also manage these settings in the control panel under **Settings → Control Panel Nav**.

## Configuration Options


::: reference
### `iconsPath`

**Type:** `string` · **Default:** `'@webroot/cpnav-icons/'`

The filesystem folder containing your custom SVG icons. The default is `@webroot/cpnav-icons/`. Craft aliases and environment variables are supported.
:::
