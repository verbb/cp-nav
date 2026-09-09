<?php
namespace verbb\cpnav\nav\sources;

use Craft;
use craft\helpers\Json;

/**
 * Fingerprints install inputs that affect which nav source nodes exist.
 */
final class NavFingerprint
{
    // Public Methods
    // =========================================================================

    public function compute(): string
    {
        $general = Craft::$app->getConfig()->getGeneral();
        $payload = [
            'craft' => Craft::$app->getVersion(),
            'edition' => (string)Craft::$app->edition->value,
            // Cached labels are already translated — partition by CP language.
            'language' => Craft::$app->language,
            'enableGql' => (bool)$general->enableGql,
            'allowAdminChanges' => (bool)$general->allowAdminChanges,
            'disabledPlugins' => $general->disabledPlugins ?? [],
            'plugins' => $this->_pluginSnapshot(),
            'sections' => $this->_uidList('{{%sections}}'),
            'volumes' => $this->_uidList('{{%volumes}}'),
            'globalSets' => $this->_uidList('{{%globalsets}}'),
            'categoryGroups' => $this->_uidList('{{%categorygroups}}'),
            'entryPages' => $this->_entryPages(),
            // Commerce (and similar) conditional subnav depends on product type set.
            'commerceProductTypes' => $this->_commerceProductTypeUids(),
        ];

        return hash('sha256', Json::encode($payload));
    }


    // Private Methods
    // =========================================================================

    private function _pluginSnapshot(): array
    {
        $rows = [];

        foreach (Craft::$app->getPlugins()->getAllPlugins() as $plugin) {
            $rows[] = [
                'handle' => $plugin->handle,
                'version' => $plugin->getVersion(),
                'hasCpSection' => (bool)$plugin->hasCpSection,
            ];
        }

        usort($rows, fn(array $a, array $b) => strcmp($a['handle'], $b['handle']));

        return $rows;
    }

    private function _uidList(string $table): array
    {
        return Craft::$app->getDb()
            ->createCommand("SELECT [[uid]] FROM {$table} ORDER BY [[uid]]")
            ->queryColumn();
    }

    private function _entryPages(): array
    {
        if (!Craft::$app->getEntries()->getAllSections()) {
            return [];
        }

        return Craft::$app->getElementSources()->getPages(\craft\elements\Entry::class);
    }

    private function _commerceProductTypeUids(): array
    {
        $db = Craft::$app->getDb();

        if (!$db->tableExists('{{%commerce_producttypes}}')) {
            return [];
        }

        return $this->_uidList('{{%commerce_producttypes}}');
    }
}
