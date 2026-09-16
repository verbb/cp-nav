<?php
namespace verbb\cpnav\migrations;

use verbb\cpnav\CpNav;

use craft\db\Migration;

class m260916_130000_new_item_baseline extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $customization = CpNav::$plugin->getNavCustomization();

        foreach (CpNav::$plugin->getLayouts()->getAllLayouts() as $layout) {
            if ($customization->getAcknowledgedRegistryKeys($layout->uid) === null) {
                $customization->acknowledgeCurrentRegistry($layout->uid);
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260916_130000_new_item_baseline cannot be reverted.\n";

        return false;
    }
}
