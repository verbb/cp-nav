<?php
require __DIR__ . '/verify.php';
foreach (['Browser editors', 'Browser managers', 'Browser drag'] as $name) {
    foreach (\verbb\cpnav\CpNav::$plugin->getLayouts()->getAllLayouts() as $existing) {
        if ($existing->name === $name) {
            \verbb\cpnav\CpNav::$plugin->getLayouts()->deleteLayout($existing);
        }
    }
    $layout = new \verbb\cpnav\models\Layout(['name' => $name]);
    if (!\verbb\cpnav\CpNav::$plugin->getLayouts()->saveLayout($layout)) {
        throw new RuntimeException('Could not seed browser layout.');
    }
}

$groups = Craft::$app->getUserGroups();
if (!$groups->getGroupByHandle('browserEditors')) {
    $group = new \craft\models\UserGroup(['name' => 'Browser editors', 'handle' => 'browserEditors']);
    if (!$groups->saveGroup($group)) {
        throw new RuntimeException('Could not seed browser user group.');
    }
}

$icons = Craft::$app->getPath()->getStoragePath() . '/browser-icons';
\craft\helpers\FileHelper::createDirectory($icons);
file_put_contents($icons . '/mark.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M0 0h16v16z"/></svg>');
// An owned hostile fixture verifies that opening an SVG cannot run same-origin scripts.
file_put_contents($icons . '/script-probe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>window.__cpnavSvgScript = 1</script></svg>');
if (!Craft::$app->getPlugins()->savePluginSettings(\verbb\cpnav\CpNav::$plugin, ['iconsPath' => $icons])) {
    throw new RuntimeException('Could not configure browser icon fixture.');
}
Craft::$app->getProjectConfig()->saveModifiedConfigData();
