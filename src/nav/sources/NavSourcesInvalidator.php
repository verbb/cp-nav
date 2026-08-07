<?php
namespace verbb\cpnav\nav\sources;

use verbb\cpnav\CpNav;

use Craft;
use craft\services\Plugins;

use yii\base\Event;

/**
 * Tier-1 nav sources invalidation hooks.
 *
 * Fingerprint-on-read in {@see NavSources::getTree()} remains the fallback when
 * an event is missed; these hooks keep the cache warm without sync-on-read writes.
 */
final class NavSourcesInvalidator
{
    // Public Methods
    // =========================================================================

    public function register(): void
    {
        $invalidate = fn() => CpNav::$plugin->getNavSources()->invalidate();

        Event::on(Plugins::class, Plugins::EVENT_AFTER_INSTALL_PLUGIN, $invalidate);
        Event::on(Plugins::class, Plugins::EVENT_AFTER_UNINSTALL_PLUGIN, $invalidate);
        Event::on(Plugins::class, Plugins::EVENT_AFTER_ENABLE_PLUGIN, $invalidate);
        Event::on(Plugins::class, Plugins::EVENT_AFTER_DISABLE_PLUGIN, $invalidate);

        $projectConfig = Craft::$app->getProjectConfig();

        foreach (['sections', 'volumes', 'globalSets', 'categoryGroups'] as $path) {
            $projectConfig
                ->onAdd("{$path}.{uid}", $invalidate)
                ->onUpdate("{$path}.{uid}", $invalidate)
                ->onRemove("{$path}.{uid}", $invalidate);
        }

        // D25 — Commerce product types change conditional CP subnav.
        $projectConfig
            ->onAdd('commerce.productTypes.{uid}', $invalidate)
            ->onUpdate('commerce.productTypes.{uid}', $invalidate)
            ->onRemove('commerce.productTypes.{uid}', $invalidate);
    }

    /**
     * Exposed for tests — same callback wired to plugin lifecycle events.
     */
    public function invalidateSources(): void
    {
        CpNav::$plugin->getNavSources()->invalidate();
    }
}
