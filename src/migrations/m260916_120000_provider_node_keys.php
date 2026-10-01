<?php
namespace verbb\cpnav\migrations;

use verbb\cpnav\CpNav;
use verbb\cpnav\nav\sources\NodeKey;

use Craft;
use craft\db\Migration;
use craft\helpers\StringHelper;

class m260916_120000_provider_node_keys extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $keyMap = [];

        foreach (CpNav::$plugin->getNavSources()->getTree(true) as $root) {
            foreach ($root->flatten() as $node) {
                if ($node->source !== 'plugin') {
                    continue;
                }

                $handle = explode('/', substr($root->key, strlen('plugin:')), 2)[0];
                $legacyKey = 'plugin:' . StringHelper::toKebabCase(rawurldecode($handle));

                if ($node->subHandle !== null && $node->subHandle !== '') {
                    $legacyKey .= ':' . StringHelper::toKebabCase($node->subHandle);
                }
                // Old catalogs retained the last item when provider keys collided.
                $keyMap[$legacyKey] = $node->key;
            }
        }

        $config = Craft::$app->getProjectConfig();

        foreach (CpNav::$plugin->getLayouts()->getAllLayouts() as $layout) {
            $path = "cp-nav.layouts.{$layout->uid}.customizations";
            $customizations = $config->get($path);

            if (!is_array($customizations) || ($customizations['providerKeyVersion'] ?? 0) >= 2) {
                continue;
            }

            $nodes = [];

            foreach ($customizations['nodes'] ?? [] as $encoded => $node) {
                if (!is_array($node)) {
                    continue;
                }
                $key = NodeKey::decodePathKey((string)$encoded, $node['key'] ?? null);
                $node['key'] = $keyMap[$key] ?? $key;

                if (isset($node['parent'])) {
                    $node['parent'] = $keyMap[$node['parent']] ?? $node['parent'];
                }
                $nodes[NodeKey::encodePathKey($node['key'])] = $node;
            }
            $customizations['nodes'] = $nodes;

            if (isset($customizations['acknowledgedRegistryKeys'])) {
                $customizations['acknowledgedRegistryKeys'] = array_values(array_unique(array_map(
                    fn($key) => $keyMap[$key] ?? $key,
                    $customizations['acknowledgedRegistryKeys'],
                )));
            }
            $customizations['providerKeyVersion'] = 2;
            $config->set($path, $customizations, 'Preserve CP Nav customizations with distinct provider keys');
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260916_120000_provider_node_keys cannot be reverted.\n";

        return false;
    }
}
