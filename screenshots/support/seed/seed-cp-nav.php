/** Seed representative layouts and manual navigation items for CP Nav screenshots. */

use craft\helpers\Json;
use verbb\cpnav\CpNav;
use verbb\cpnav\models\Layout;
use verbb\cpnav\models\Navigation as NavigationModel;

$layouts = CpNav::$plugin->getLayouts();
$navigations = CpNav::$plugin->getNavigations();
$default = $layouts->getDefaultLayout();

if (!$default) {
    $default = new Layout(['name' => 'Default', 'isDefault' => true, 'permissions' => []]);
    if (!$layouts->saveLayout($default)) throw new RuntimeException('Unable to create default CP Nav layout.');
    $default = $layouts->getDefaultLayout();
}

$manualItems = [
    ['handle' => 'docsScreenshotDocumentation', 'label' => 'Project documentation', 'url' => 'https://docs.craftcms.com', 'icon' => 'fontIcon:book-open'],
    ['handle' => 'docsScreenshotHomepage', 'label' => 'Homepage', 'url' => 'entries/pages', 'icon' => 'fontIcon:star'],
];

foreach ($manualItems as $index => $item) {
    if ($navigations->getNavigationByHandle($item['handle'])) continue;
    $navigation = new NavigationModel([
        'layoutId' => $default->id,
        'handle' => $item['handle'],
        'prevLabel' => $item['label'],
        'currLabel' => $item['label'],
        'enabled' => true,
        'level' => 1,
        'prevLevel' => 1,
        'url' => $item['url'],
        'prevUrl' => $item['url'],
        'icon' => $item['icon'],
        'type' => NavigationModel::TYPE_MANUAL,
        'newWindow' => $index === 0,
    ]);
    if (!$navigations->saveNavigation($navigation)) throw new RuntimeException('Unable to create CP Nav item: ' . Json::encode($navigation->getErrors()));
}

echo Json::encode([
    'navigationRoute' => '/admin/cp-nav?layoutId=' . $default->id,
], JSON_THROW_ON_ERROR);
