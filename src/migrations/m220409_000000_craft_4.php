<?php
namespace verbb\cpnav\migrations;

use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\Permissions;
use verbb\cpnav\models\Navigation;
use verbb\cpnav\services\Navigations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\ArrayHelper;

use Throwable;

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

        $craftNavItems = [
            'dashboard',
            'entries',
            'categories',
            'assets',
            'users',
            'graphql',
            'utilities',
            'settings',
            'plugin-store',
        ];

        $pluginNavItems = [];

        foreach (Craft::$app->getPlugins()->getAllPlugins() as $plugin) {
            try {
                if ($plugin->hasCpSection && ($pluginNavItem = $plugin->getCpNavItem()) !== null) {
                    $pluginNavItems[] = $plugin->id;
                }
            } catch (Throwable $e) {
                // Just in case some plugins have complicated logic in their `getCpNavItem()` (like SEOmatic)
                // Skip it, but also assume that it *does* have a nav item
                $pluginNavItems[] = $plugin->id;

                continue;
            }
        }

        // Make the same changes to Project Config YAML files
        $projectConfig = Craft::$app->getProjectConfig();

        // Don't make the same config changes twice
        $schemaVersion = $projectConfig->get('plugins.cp-nav.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.8', '>=')) {
            return true;
        }

        // Update all existing navs
        $navItems = (new Query())
            ->select(['*'])
            ->from(['{{%cpnav_navigation}}'])
            ->all();

        // When we try to fetch the original nav via the CLI, it will use a guest user context. Because a lot of plugins use
        // `checkPermission()` to show their subnav, we want to override that to ensure subnavs are generated. As such, 
        // set the current identity to an admin so that the nav items can be generated properly.
        Craft::$app->getUser()->setIdentity(User::find()->admin(true)->one());

        $baseNavItems = Permissions::getBaseNavItems();
        $navigationService = CpNav::$plugin->getNavigations();

        foreach ($navItems as $navItem) {
            if (in_array($navItem['handle'], $craftNavItems)) {
                $this->update('{{%cpnav_navigation}}', ['type' => 'craft'], ['id' => $navItem['id']]);
            }

            if (in_array($navItem['handle'], $pluginNavItems)) {
                $this->update('{{%cpnav_navigation}}', ['type' => 'plugin'], ['id' => $navItem['id']]);
            }

            // Migrate any sub-nav items from Craft 3, where they didn't exist.
            $originalNav = ArrayHelper::firstWhere($baseNavItems, 'url', $navItem['url']);

            if ($originalNav) {
                $subnavs = $originalNav['subnav'] ?? [];

                if ($subnavs) {
                    foreach ($subnavs as $handle => $subnav) {
                        $subNavigation = new Navigation();
                        $subNavigation->handle = $handle;
                        $subNavigation->currLabel = $subnav['label'] ?? '';
                        $subNavigation->prevLabel = $subnav['label'] ?? '';
                        $subNavigation->enabled = true;
                        $subNavigation->url = $subnav['url'] ?? '';
                        $subNavigation->prevUrl = $subnav['url'] ?? '';
                        $subNavigation->type = $navItem['type'];
                        $subNavigation->newWindow = $subnav['external'] ?? false;
                        $subNavigation->layoutId = $navItem['layoutId'];
                        $subNavigation->prevLevel = 2;
                        $subNavigation->level = 2;
                        $subNavigation->prevParentId = $navItem['id'];
                        $subNavigation->parentId = $navItem['id'];

                        $navigationService->saveNavigation($subNavigation);
                    }
                }
            }
        }

        $navs = $projectConfig->get(Navigations::CONFIG_NAVIGATION_KEY);

        if (is_array($navs)) {
            foreach ($navs as $navUid => $nav) {
                $nav['sortOrder'] = $nav['order'] ?? 0;
                unset($nav['order']);

                $nav['prevLevel'] = $nav['prevLevel'] ?? null;
                $nav['level'] = $nav['level'] ?? 1;
                $nav['prevParentId'] = $nav['prevParentId'] ?? null;
                $nav['parentId'] = $nav['parentId'] ?? null;

                if (in_array($nav['handle'], $craftNavItems)) {
                    $nav['type'] = 'craft';
                }

                if (in_array($nav['handle'], $pluginNavItems)) {
                    $nav['type'] = 'plugin';
                }

                $projectConfig->set(Navigations::CONFIG_NAVIGATION_KEY . '.' . $navUid, $nav);
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

