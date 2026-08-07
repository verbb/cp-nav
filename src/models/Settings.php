<?php
namespace verbb\cpnav\models;

use craft\base\Model;

class Settings extends Model
{
    // Public Methods
    // =========================================================================

    public function __construct($config = [])
    {
        // Remove deprecated settings from older installs / project config.
        unset($config['originalNavHash'], $config['subnavBehaviour']);

        parent::__construct($config);
    }

}
