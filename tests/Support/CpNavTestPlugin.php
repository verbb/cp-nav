<?php

declare(strict_types=1);

namespace Tests\Support;

use craft\db\Query;
use Craft;
use ReflectionClass;

final class CpNavTestPlugin
{
    public static function ensureInstalledAndMigrated(): void
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $pluginRows = (new Query())
            ->select(['handle', 'schemaVersion'])
            ->from('{{%plugins}}')
            ->where(['handle' => 'cp-nav'])
            ->all();

        foreach ($pluginRows as $pluginRow) {
            $handle = (string)($pluginRow['handle'] ?? '');
            if (!$handle) {
                continue;
            }

            $key = 'plugins.' . $handle;
            $pluginConfig = $projectConfig->get($key);

            if (!$pluginConfig || empty($pluginConfig['enabled'])) {
                $projectConfig->set($key, [
                    ...($pluginConfig ?: []),
                    'edition' => 'standard',
                    'enabled' => true,
                    'schemaVersion' => (string)($pluginConfig['schemaVersion'] ?? $pluginRow['schemaVersion'] ?? ''),
                ]);
            }
        }

        $pluginsReflection = new ReflectionClass(Craft::$app->plugins);
        foreach ([
            '_pluginsLoaded' => false,
            '_loadingPlugins' => false,
            '_plugins' => [],
        ] as $propertyName => $value) {
            if (!$pluginsReflection->hasProperty($propertyName)) {
                continue;
            }

            $property = $pluginsReflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue(Craft::$app->plugins, $value);
        }

        $plugins = Craft::$app->plugins;

        if (!$plugins->isPluginInstalled('cp-nav')) {
            $plugins->installPlugin('cp-nav');
        } elseif (!$plugins->isPluginEnabled('cp-nav')) {
            $plugins->enablePlugin('cp-nav');
        }

        $plugin = $plugins->getPlugin('cp-nav');
        if (!$plugin) {
            throw new \RuntimeException('CP Nav plugin failed to load for integration tests.');
        }

        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;

        try {
            $migrator = $plugin->getMigrator();

            foreach ($migrator->getNewMigrations() as $migration) {
                $migrator->migrateUp($migration);
            }

            if ($plugins->isPluginUpdatePending($plugin)) {
                $plugins->updatePluginVersionInfo($plugin);
            }
        } finally {
            $projectConfig->readOnly = $readOnly;
        }
    }
}
