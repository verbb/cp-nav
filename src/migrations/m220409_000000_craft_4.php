<?php
namespace verbb\cpnav\migrations;

use verbb\cpnav\services\NavigationsService;

use Craft;
use craft\db\Migration;

class m220409_000000_craft_4 extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->columnExists('{{%cpnav_navigation}}', 'order') && !$this->db->columnExists('{{%cpnav_navigation}}', 'sortOrder')) {
            $this->renameColumn('{{%cpnav_navigation}}', 'order', 'sortOrder');
        }

        if (!$this->db->columnExists('{{%cpnav_navigation}}', 'prevLevel')) {
            $this->addColumn('{{%cpnav_navigation}}', 'prevLevel', $this->smallInteger()->after('sortOrder'));
        }

        if (!$this->db->columnExists('{{%cpnav_navigation}}', 'level')) {
            $this->addColumn('{{%cpnav_navigation}}', 'level', $this->smallInteger()->defaultValue(1)->after('prevLevel'));
        }

        if (!$this->db->columnExists('{{%cpnav_navigation}}', 'prevParentId')) {
            $this->addColumn('{{%cpnav_navigation}}', 'prevParentId', $this->integer()->after('level'));
        }

        if (!$this->db->columnExists('{{%cpnav_navigation}}', 'parentId')) {
            $this->addColumn('{{%cpnav_navigation}}', 'parentId', $this->integer()->after('prevParentId'));
        }

        if (!$this->db->columnExists('{{%cpnav_navigation}}', 'subnavBehaviour')) {
            $this->addColumn('{{%cpnav_navigation}}', 'subnavBehaviour', $this->string()->after('newWindow'));
        }

        $this->update('{{%cpnav_navigation}}', [
            'level' => 1,
        ]);

        $this->dropTableIfExists('{{%cpnav_pending_navigations}}');

        $projectConfig = Craft::$app->getProjectConfig();

        // Don't make the same config changes twice
        $schemaVersion = $projectConfig->get('plugins.cp-nav.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.8', '>=')) {
            return true;
        }

        $navs = $projectConfig->get(NavigationsService::CONFIG_NAVIGATION_KEY);

        if (is_array($navs)) {
            foreach ($navs as $navUid => $nav) {
                $nav['sortOrder'] = $nav['order'] ?? 0;
                unset($nav['order']);

                $nav['prevLevel'] = null;
                $nav['level'] = 1;
                $nav['prevParentId'] = null;
                $nav['parentId'] = null;

                $projectConfig->set(NavigationsService::CONFIG_NAVIGATION_KEY . '.' . $navUid, $nav);
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m220409_000000_craft_4 cannot be reverted.\n";
        return false;
    }
}

