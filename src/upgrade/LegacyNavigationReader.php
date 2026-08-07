<?php
namespace verbb\cpnav\upgrade;

use verbb\cpnav\models\LayoutNavItem;

use Craft;
use craft\db\Query;
use craft\helpers\ArrayHelper;

/**
 * Reads v5 navigation rows from the live or archived DB table.
 */
final class LegacyNavigationReader
{
    // Public Methods
    // =========================================================================

    public function getLayoutNavItemsForLayout(int $layoutId): array
    {
        $table = $this->_legacyTable();

        if (!$table) {
            return [];
        }

        $navigations = [];

        foreach ($this->_createLayoutNavItemQuery($table)->andWhere(['layoutId' => $layoutId])->all() as $result) {
            $navigations[] = new LayoutNavItem($result);
        }

        foreach ($navigations as $navigation) {
            $parentId = $navigation->parentId ?? $navigation->prevParentId;

            if ($parentId && $parentNavigation = ArrayHelper::firstWhere($navigations, 'id', $parentId)) {
                $parentNavigation->addChild($navigation);
            }

            if ($navigation->prevParentId && $parentNavigation = ArrayHelper::firstWhere($navigations, 'id', $navigation->prevParentId)) {
                $parentNavigation->addPrevChild($navigation);
            }
        }

        return $navigations;
    }


    // Private Methods
    // =========================================================================

    private function _legacyTable(): ?string
    {
        $db = Craft::$app->getDb();

        if ($db->tableExists('{{%cpnav_navigation}}')) {
            return '{{%cpnav_navigation}}';
        }

        if ($db->tableExists('{{%cpnav_navigation_v5_archive}}')) {
            return '{{%cpnav_navigation_v5_archive}}';
        }

        return null;
    }

    private function _createLayoutNavItemQuery(string $table): Query
    {
        return (new Query())
            ->select([
                'id',
                'layoutId',
                'handle',
                'prevLabel',
                'currLabel',
                'enabled',
                'sortOrder',
                'prevLevel',
                'level',
                'prevParentId',
                'parentId',
                'prevUrl',
                'url',
                'icon',
                'customIcon',
                'type',
                'newWindow',
                'dateUpdated',
                'dateCreated',
                'uid',
            ])
            ->from([$table])
            ->orderBy(['sortOrder' => SORT_ASC]);
    }
}
