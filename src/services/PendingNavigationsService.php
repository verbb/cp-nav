<?php
namespace verbb\cpnav\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Json;

class PendingNavigationsService extends Component
{
    // Public Methods
    // =========================================================================

    public function get()
    {
        $navItems = (new Query())
            ->select(['pluginNavItem'])
            ->from('{{%cpnav_pending_navigations}}')
            ->column();

        $items = [];

        foreach ($navItems as $navItem) {
            $items[] = Json::decode($navItem);
        }

        return $items;
    }

    public function set($pluginNavItem)
    {
        $exists = false;

        // Probably a better way to do this, but can't be bothered adding a new column to the table
        $navItems = (new Query())
            ->select(['pluginNavItem'])
            ->from('{{%cpnav_pending_navigations}}')
            ->column();

        foreach ($navItems as $navItem) {
            $json = Json::decode($navItem);

            if ($json['url'] === $pluginNavItem['url']) {
                $exists = true;
            }
        }

        if ($exists) {
            return;
        }

        Craft::$app->getDb()->createCommand()
            ->insert('{{%cpnav_pending_navigations}}', ['pluginNavItem' => Json::encode($pluginNavItem)])
            ->execute();
    }

    public function remove()
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%cpnav_pending_navigations}}')
            ->execute();
    }
}
