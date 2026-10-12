<?php
namespace verbb\cpnav\migrations;

use verbb\cpnav\CpNav;
use verbb\cpnav\upgrade\CustomizationUpgradeService;

use Craft;
use craft\db\Migration;

class m260711_120000_retire_v5_navigation extends Migration
{
    // Constants
    // =========================================================================

    private const LEGACY_NAVIGATION_PC_KEY = 'cp-nav.navigations';


    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%cpnav_navigation}}')) {
            return true;
        }

        $migrationService = new CustomizationUpgradeService();

        foreach (CpNav::$plugin->getLayouts()->getAllLayouts() as $layout) {
            $overlay = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);

            if ($overlay !== []) {
                continue;
            }

            $migrationService->upgradeLayouts($layout->uid, false);
        }

        Craft::$app->getProjectConfig()->remove(self::LEGACY_NAVIGATION_PC_KEY);

        $this->_dropAllForeignKeysOnTable('{{%cpnav_navigation}}');
        $this->renameTable('{{%cpnav_navigation}}', '{{%cpnav_navigation_v5_archive}}');

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260711_120000_retire_v5_navigation cannot be reverted.\n";

        return false;
    }


    // Private Methods
    // =========================================================================

    private function _dropAllForeignKeysOnTable(string $tableName): void
    {
        $schema = $this->db->getSchema();
        $schema->refresh();
        $table = $schema->getTableSchema($schema->getRawTableName($tableName));

        foreach ($table?->foreignKeys ?? [] as $foreignKey) {
            $columns = array_keys($foreignKey);
            array_shift($columns);
            $this->dropForeignKeyIfExists($tableName, $columns);
        }
    }
}
