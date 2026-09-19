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

foreach (['Client', 'SEO Contractors'] as $name) {
    $exists = null;
    foreach ($layouts->getAllLayouts() as $layout) if ($layout->name === $name) $exists = $layout;
    if (!$exists) {
        $layout = new Layout(['name' => $name, 'isDefault' => false, 'permissions' => []]);
        if (!$layouts->saveLayout($layout)) throw new RuntimeException('Unable to create CP Nav layout ' . $name);
    }
}

$manualItems = [
    ['handle' => 'docsScreenshotGoogle', 'label' => 'Google', 'url' => 'https://google.com', 'icon' => 'fontIcon:globe'],
    ['handle' => 'docsScreenshotHighlighted', 'label' => 'Highlighted Entry', 'url' => 'entries/pages', 'icon' => 'fontIcon:star'],
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
    'layoutsRoute' => '/admin/cp-nav/layouts',
], JSON_THROW_ON_ERROR);
