<?php
namespace verbb\cpnav\nav\render;

use verbb\cpnav\nav\resolve\ResolvedNavNode;
use verbb\cpnav\nav\sources\NavSourceBuilder;
use verbb\cpnav\nav\sources\NodeKey;

use Craft;
use craft\base\Component;

/** Applies the current user's native menu visibility to the customized navigation. */
final class NavPermissions extends Component
{
    // Public Methods
    // =========================================================================

    public function filter(array $resolved, array $registryTree, ?array $navItems = null): array
    {
        // The registry is an admin catalog, never evidence of another user's access.
        // During sidebar rendering, reuse the live event so providers run only once for this user.
        if ($navItems !== null) {
            $keys = $this->_keysFromNavItems($navItems);
        } else {
            $identity = Craft::$app->getUser()->getIdentity();
            $keys = $identity ? (new NavSourceBuilder())->buildKeys($identity) : [];
        }
        $visible = array_fill_keys($keys, true);

        return array_values(array_filter(
            $resolved,
            fn(ResolvedNavNode $node) => NodeKey::isCustomizationOnly($node->key) || isset($visible[$node->key]),
        ));
    }


    // Private Methods
    // =========================================================================

    private function _keysFromNavItems(array $navItems, ?string $parentKey = null): array
    {
        $keys = [];

        foreach ($navItems as $handle => $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = NodeKey::fromNavItem($item, $parentKey, (string)$handle);
            $keys[] = $key;

            if (isset($item['subnav']) && is_array($item['subnav'])) {
                array_push($keys, ...$this->_keysFromNavItems($item['subnav'], $key));
            }
        }

        return $keys;
    }
}
