<?php
namespace verbb\cpnav\nav\render;

use verbb\cpnav\nav\resolve\ResolvedNavNode;
use verbb\cpnav\nav\sources\NavNode;
use verbb\cpnav\nav\sources\NodeKey;

use Craft;
use craft\base\Component;
use craft\enums\CmsEdition;
use craft\helpers\StringHelper;

/**
 * Live permission filtering for resolved nav nodes.
 */
final class NavPermissions extends Component
{
    // Public Methods
    // =========================================================================

    public function filter(array $resolved, array $registryTree): array
    {
        $registryIndex = $this->_indexRegistry($registryTree);

        return array_values(array_filter(
            $resolved,
            fn(ResolvedNavNode $node) => $this->_canView($node, $registryIndex[$node->key] ?? null),
        ));
    }


    // Private Methods
    // =========================================================================

    private function _indexRegistry(array $registryTree): array
    {
        $index = [];

        $walk = function(array $nodes) use (&$index, &$walk): void {
            foreach ($nodes as $node) {
                $index[$node->key] = $node;
                if ($node->children) {
                    $walk($node->children);
                }
            }
        };

        $walk($registryTree);

        return $index;
    }

    private function _canView(ResolvedNavNode $node, ?NavNode $registryNode): bool
    {
        if (NodeKey::isCustomizationOnly($node->key)) {
            return true;
        }

        if (str_starts_with($node->key, NodeKey::NS_PLUGIN . ':')) {
            return $this->_canViewPluginNode($node);
        }

        return $this->_canViewCraftNode($node);
    }

    private function _canViewCraftNode(ResolvedNavNode $node): bool
    {
        [, $path] = NodeKey::split($node->key);
        $segment = explode('/', $path)[0] ?? $path;
        $user = Craft::$app->getUser();
        $general = Craft::$app->getConfig()->getGeneral();

        if ($segment === 'dashboard' || $path === 'dashboard') {
            return true;
        }

        if ($segment === 'content' || $path === 'entries') {
            return (bool)Craft::$app->getEntries()->getTotalEditableSections();
        }

        if ($segment === 'globals') {
            return (bool)Craft::$app->getGlobals()->getEditableSets();
        }

        if ($segment === 'categories') {
            return (bool)Craft::$app->getCategories()->getEditableGroupIds();
        }

        if ($segment === 'assets') {
            return (bool)Craft::$app->getVolumes()->getTotalViewableVolumes();
        }

        if ($segment === 'users') {
            return Craft::$app->edition !== CmsEdition::Solo
                && $user->checkPermission('viewUsers');
        }

        if ($segment === 'utilities') {
            return (bool)Craft::$app->getUtilities()->getAuthorizedUtilityTypes();
        }

        if ($segment === 'graphql' || str_starts_with($path, 'graphql/')) {
            return $user->getIsAdmin() && $general->enableGql;
        }

        // Craft still shows Settings when allowAdminChanges=false (gear-slash icon).
        if ($segment === 'settings') {
            return $user->getIsAdmin();
        }

        if ($segment === 'plugin-store') {
            return $user->getIsAdmin();
        }

        if ($segment === 'graphiql') {
            return $user->getIsAdmin() && $general->enableGql;
        }

        // Event-registered or unknown craft paths — allow if present in nav sources build.
        return true;
    }

    private function _canViewPluginNode(ResolvedNavNode $node): bool
    {
        $parts = explode(':', $node->key);
        $pluginHandle = $parts[1] ?? null;

        if (!$pluginHandle) {
            return false;
        }

        $plugin = Craft::$app->getPlugins()->getPlugin($pluginHandle);
        if (!$plugin || !$plugin->hasCpSection) {
            return false;
        }

        if (!Craft::$app->getUser()->checkPermission('accessPlugin-' . $plugin->id)) {
            return false;
        }

        // Match Craft: plugins may deny the section for this user by returning null.
        $pluginNavItem = $plugin->getCpNavItem();
        if ($pluginNavItem === null) {
            return false;
        }

        $subHandle = $parts[2] ?? null;
        if (!$subHandle) {
            return true;
        }

        $subnav = $pluginNavItem['subnav'] ?? null;
        if (!is_array($subnav)) {
            return false;
        }

        // Provider keys are authoritative — try stored segment, then kebab form.
        if (array_key_exists($subHandle, $subnav)) {
            return true;
        }

        $kebab = StringHelper::toKebabCase($subHandle);

        return $kebab !== $subHandle && array_key_exists($kebab, $subnav);
    }
}
