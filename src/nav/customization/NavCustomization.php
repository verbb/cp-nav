<?php
namespace verbb\cpnav\nav\customization;

use verbb\cpnav\CpNav;
use verbb\cpnav\events\CustomizationEvent;
use verbb\cpnav\nav\sources\NodeKey;
use verbb\cpnav\services\Layouts;

use Craft;
use craft\base\Component;

/**
 * Reads and writes per-layout nav customizations in project config.
 *
 * Storage path:
 * `cp-nav.layouts.{layoutUid}.customizations.nodes.{encodedKey}` → customization node value
 */
class NavCustomization extends Component
{
    // Constants
    // =========================================================================

    public const EVENT_AFTER_SAVE_NODE = 'afterSaveNode';
    public const EVENT_AFTER_REMOVE_NODE = 'afterRemoveNode';
    public const EVENT_AFTER_SET_NODES = 'afterSetNodes';


    // Public Methods
    // =========================================================================

    public function getCustomizationForLayout(string $layoutUid): array
    {
        $nodes = Craft::$app->getProjectConfig()->get($this->nodesPath($layoutUid)) ?? [];
        if (!is_array($nodes)) {
            return [];
        }

        $customizations = [];

        foreach ($nodes as $encodedKey => $config) {
            if (!is_array($config)) {
                continue;
            }

            $node = CustomizationNode::fromConfig((string)$encodedKey, $config);
            $customizations[$node->key] = $node;
        }

        return $customizations;
    }

    public function getCustomizationForCurrentUser(): array
    {
        $layout = CpNav::$plugin->getLayouts()->getLayoutForCurrentUser();

        return $layout ? $this->getCustomizationForLayout($layout->uid) : [];
    }

    public function saveNode(string $layoutUid, CustomizationNode $node): void
    {
        $path = $this->nodePath($layoutUid, $node->key);
        $this->_removeLegacyNode($layoutUid, $node->key);

        Craft::$app->getProjectConfig()->set(
            $path,
            $node->toConfig(),
            "Save CP Nav customization for {$node->key}",
        );
        $this->_markProviderKeysCurrent($layoutUid);

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_NODE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_NODE, new CustomizationEvent([
                'layoutUid' => $layoutUid,
                'node' => $node,
                'nodeKey' => $node->key,
            ]));
        }
    }

    public function removeNode(string $layoutUid, string $canonicalKey): void
    {
        $this->_removeLegacyNode($layoutUid, $canonicalKey);
        Craft::$app->getProjectConfig()->remove(
            $this->nodePath($layoutUid, $canonicalKey),
            "Remove CP Nav customization for {$canonicalKey}",
        );

        if ($this->hasEventHandlers(self::EVENT_AFTER_REMOVE_NODE)) {
            $this->trigger(self::EVENT_AFTER_REMOVE_NODE, new CustomizationEvent([
                'layoutUid' => $layoutUid,
                'nodeKey' => $canonicalKey,
            ]));
        }
    }

    public function setCustomizationNodes(string $layoutUid, array $nodes, bool $migrationComplete = false): void
    {
        $payload = [];

        foreach ($nodes as $node) {
            $payload[NodeKey::encodePathKey($node->key)] = $node->toConfig();
        }

        $path = $this->nodesPath($layoutUid);
        if ($migrationComplete) {
            // Commit conversion and its completion marker as one PC change. A reset
            // is also intentional v6 state, not permission to resurrect archived rows.
            $path = Layouts::CONFIG_LAYOUT_KEY . ".{$layoutUid}.customizations";
            $payload = array_merge(Craft::$app->getProjectConfig()->get($path) ?? [], [
                'nodes' => $payload,
                'migrationVersion' => 1,
                'providerKeyVersion' => 2,
            ]);
        }
        Craft::$app->getProjectConfig()->set(
            $path,
            $payload,
            'Save CP Nav customization nodes',
        );
        $this->_markProviderKeysCurrent($layoutUid);

        if ($this->hasEventHandlers(self::EVENT_AFTER_SET_NODES)) {
            $this->trigger(self::EVENT_AFTER_SET_NODES, new CustomizationEvent([
                'layoutUid' => $layoutUid,
            ]));
        }
    }

    public function clearCustomization(string $layoutUid): void
    {
        $this->setCustomizationNodes($layoutUid, [], true);
    }

    /** Remove stale canonical customization keys that are no longer in nav sources. */
    public function removeStaleNodes(string $layoutUid, array $staleKeys): array
    {
        $removed = [];

        foreach ($staleKeys as $key) {
            if (NodeKey::isCustomizationOnly($key)) {
                continue;
            }

            $this->removeNode($layoutUid, $key);
            $removed[] = $key;
        }

        return $removed;
    }

    /**
     * Keys the admin has seen for this layout, or null when tracking has never started.
     * This state is used only for "new item" notices.
     */
    public function getAcknowledgedRegistryKeys(string $layoutUid): ?array
    {
        $keys = Craft::$app->getProjectConfig()->get($this->acknowledgedRegistryKeysPath($layoutUid));

        if (!is_array($keys)) {
            return null;
        }

        return array_values($keys);
    }

    public function setAcknowledgedRegistryKeys(string $layoutUid, array $keys): void
    {
        Craft::$app->getProjectConfig()->set(
            $this->acknowledgedRegistryKeysPath($layoutUid),
            array_values(array_unique($keys)),
            'Acknowledge CP Nav registry keys for layout',
        );
        $this->_markProviderKeysCurrent($layoutUid);
    }

    /** Snapshot current nav registry keys so reset/dismiss does not show a false "new items" banner. */
    public function acknowledgeCurrentRegistry(string $layoutUid): void
    {
        $keys = [];

        $walk = function(array $nodes) use (&$keys, &$walk): void {
            foreach ($nodes as $node) {
                $keys[] = $node->key;

                if ($node->children) {
                    $walk($node->children);
                }
            }
        };

        $walk(CpNav::$plugin->getNavSources()->getTree());
        $this->setAcknowledgedRegistryKeys($layoutUid, $keys);
    }

    public function nodesPath(string $layoutUid): string
    {
        return Layouts::CONFIG_LAYOUT_KEY
            . ".{$layoutUid}."
            . CustomizationSchema::CUSTOMIZATIONS_KEY
            . '.'
            . CustomizationSchema::NODES_KEY;
    }

    public function nodePath(string $layoutUid, string $canonicalKey): string
    {
        return $this->nodesPath($layoutUid) . '.' . NodeKey::encodePathKey($canonicalKey);
    }

    public function acknowledgedRegistryKeysPath(string $layoutUid): string
    {
        return Layouts::CONFIG_LAYOUT_KEY
            . ".{$layoutUid}."
            . CustomizationSchema::CUSTOMIZATIONS_KEY
            . '.'
            . CustomizationSchema::ACKNOWLEDGED_REGISTRY_KEYS_KEY;
    }


    // Private Methods
    // =========================================================================

    private function _markProviderKeysCurrent(string $layoutUid): void
    {
        // Current identities must not be reinterpreted as legacy keys when this
        // configuration is deployed before the target runs its pending migration.
        Craft::$app->getProjectConfig()->set(
            Layouts::CONFIG_LAYOUT_KEY . ".{$layoutUid}.customizations.providerKeyVersion",
            2,
            'Save CP Nav provider key version',
        );
    }

    private function _removeLegacyNode(string $layoutUid, string $canonicalKey): void
    {
        $encoded = NodeKey::encodePathKeyLegacy($canonicalKey);
        $path = $this->nodesPath($layoutUid) . '.' . $encoded;
        $config = Craft::$app->getProjectConfig()->get($path);

        // Legacy paths can collide. Only remove a row that reads back as this identity.
        if (is_array($config) && CustomizationNode::fromConfig($encoded, $config)->key === $canonicalKey) {
            Craft::$app->getProjectConfig()->remove($path, "Remove legacy CP Nav path for {$canonicalKey}");
        }
    }
}
