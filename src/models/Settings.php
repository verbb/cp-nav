<?php
namespace verbb\cpnav\models;

use craft\base\Model;
use craft\helpers\App;
use craft\helpers\FileHelper;

class Settings extends Model
{
    // Properties
    // =========================================================================

    /**
     * Folder of SVG icons for nav customizations. Relative paths are stored on
     * nodes for Project Config portability — not Craft assets.
     */
    public string $iconsPath = '@webroot/cpnav-icons/';


    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
        // Remove deprecated settings from older installs / project config.
        unset($config['originalNavHash'], $config['subnavBehaviour']);

        parent::__construct($config);
    }

    public function getIconsPath(): string
    {
        if ($this->iconsPath) {
            return FileHelper::normalizePath(App::parseEnv($this->iconsPath));
        }

        return $this->iconsPath;
    }
}
