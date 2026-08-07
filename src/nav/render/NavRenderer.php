<?php
namespace verbb\cpnav\nav\render;

use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\CustomIcon;
use verbb\cpnav\nav\resolve\ResolvedNavNode;
use verbb\cpnav\nav\sources\NavSourceBuilder;
use verbb\cpnav\nav\sources\NodeKey;

use Craft;
use craft\events\RegisterCpNavItemsEvent;
use craft\helpers\StringHelper;
use craft\services\Plugins;
use craft\web\twig\variables\Cp;

use yii\base\Component;
use yii\base\Event;

/**
 * Injects resolved nav into Craft's CP nav pipeline.
 */
final class NavRenderer extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Register after all plugins load so our handler runs after other nav modifiers.
     */
    public function register(): void
    {
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_LOAD_PLUGINS,
            fn() => Event::on(
                Cp::class,
                Cp::EVENT_REGISTER_CP_NAV_ITEMS,
                [$this, 'onRegisterCpNavItems'],
            ),
        );
    }

    public function onRegisterCpNavItems(RegisterCpNavItemsEvent $event): void
    {
        // NavSourceBuilder captures raw Craft nav during getTree(); skip our merge pass then.
        if (NavSourceBuilder::isCapturing()) {
            return;
        }

        if (!Craft::$app->getUser()->getIdentity()) {
            return;
        }

        // D27 — missing layout falls back to default; never fatal.
        $layout = CpNav::$plugin->getLayouts()->getLayoutForCurrentUser()
            ?? CpNav::$plugin->getLayouts()->getDefaultLayout();

        if (!$layout) {
            return;
        }

        // Badge counts are request-dynamic — harvest from Craft's own nav before we replace it.
        $badgeCounts = $this->_badgeCountsFromCraftNav($event->navItems);

        $registryTree = CpNav::$plugin->getNavSources()->getTree();
        $customizations = CpNav::$plugin->getNavCustomization()->getCustomizationForLayout($layout->uid);
        $resolved = CpNav::$plugin->getNavResolver()->resolve($registryTree, $customizations, $layout->uid);
        $resolved = CpNav::$plugin->getNavPermissions()->filter($resolved, $registryTree);

        $event->navItems = $this->toCraftNavItems($registryTree, $resolved, $badgeCounts);
    }

    /**
     * @param ResolvedNavNode[] $resolvedNodes
     * @param array<string, int> $badgeCounts keyed by node key
     */
    public function toCraftNavItems(array $registryTree, array $resolvedNodes, array $badgeCounts = []): array
    {
        $registryIndex = $this->_indexRegistry($registryTree);
        $byParent = [];

        foreach ($resolvedNodes as $node) {
            if (!$node->enabled) {
                continue;
            }

            $parent = $node->parentKey ?? '';
            $byParent[$parent][] = $node;
        }

        foreach ($byParent as &$siblings) {
            usort($siblings, fn(ResolvedNavNode $a, ResolvedNavNode $b) => $a->sort <=> $b->sort ?: strcmp($a->key, $b->key));
        }
        unset($siblings);

        return $this->_buildLevel($byParent, '', $registryIndex, $badgeCounts);
    }

    /** Substitute `{site}` / `{siteHandle}` tokens in manual URLs (D24). */
    public function substituteSiteTokens(string $url): string
    {
        if (!str_contains($url, '{site')) {
            return $url;
        }

        $site = Craft::$app->getSites()->getCurrentSite();

        return str_replace(
            ['{site}', '{siteHandle}'],
            [(string)$site->id, $site->handle],
            $url,
        );
    }


    // Private Methods
    // =========================================================================

    private function _buildLevel(array $byParent, string $parentKey, array $registryIndex, array $badgeCounts): array
    {
        $items = [];

        foreach ($byParent[$parentKey] ?? [] as $resolved) {
            if (NodeKey::isDivider($resolved->key)) {
                // Section break — inert Craft nav item; JS tags `data-type="divider"` for CSS.
                $items[] = $this->_dividerNavItem($resolved);
                continue;
            }

            $registry = $registryIndex[$resolved->key] ?? null;
            // User-uploaded SVG overrides any Craft/plugin icon (font or path).
            $customIconPath = $this->_customIconPath($resolved->customIcon);
            $icon = $customIconPath ?: ($resolved->icon ?? $registry?->icon);
            $url = $this->substituteSiteTokens($resolved->url);

            $item = [
                'label' => $resolved->label,
                'url' => $this->_relativeUrl($url),
            ];

            if ($icon) {
                $this->_applyIcon($item, $icon, (bool)$customIconPath);
            }

            // Craft `external` → target=_blank + sidebar icon. Driven only by resolved newWindow
            // (overlay, or registry defaultExternal e.g. GraphiQL) — not by URL scheme. Otherwise
            // placeholders like `https://` on new manuals would look/act as new-window links.
            $item['external'] = $resolved->newWindow;

            if (isset($badgeCounts[$resolved->key]) && $badgeCounts[$resolved->key] > 0) {
                $item['badgeCount'] = $badgeCounts[$resolved->key];
            }

            $children = $this->_buildLevel($byParent, $resolved->key, $registryIndex, $badgeCounts);

            if ($children !== []) {
                $item['subnav'] = [];
                foreach ($children as $child) {
                    $handle = $this->_subnavHandle($child, $resolved->key);
                    $item['subnav'][$handle] = $child;
                }
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Divider payload for Craft's RegisterCpNavItemsEvent.
     * Stable `id` is what CP Nav JS uses to find and style the sidebar row.
     */
    private function _dividerNavItem(ResolvedNavNode $resolved): array
    {
        $uuid = NodeKey::dividerUuid($resolved->key) ?? StringHelper::toKebabCase($resolved->key);

        return [
            'id' => 'nav-divider-' . $uuid,
            'label' => $resolved->label,
            // Sentinel path — never matches a real CP route; href is stripped via linkAttributes.
            'url' => '__cpnav-divider',
            'linkAttributes' => [
                'href' => false,
                'tabindex' => -1,
                'role' => 'presentation',
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function _badgeCountsFromCraftNav(array $navItems, ?string $parentKey = null): array
    {
        $counts = [];

        foreach ($navItems as $handle => $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = NodeKey::fromNavItem($item, $parentKey, is_string($handle) ? $handle : null);
            $badge = $item['badgeCount'] ?? null;

            if (is_numeric($badge) && (int)$badge > 0) {
                $counts[$key] = (int)$badge;
            }

            if (!empty($item['subnav']) && is_array($item['subnav'])) {
                $counts += $this->_badgeCountsFromCraftNav($item['subnav'], $key);
            }
        }

        return $counts;
    }

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

    private function _relativeUrl(string $url): string
    {
        // External / absolute URLs pass through after site-token substitution.
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '//')) {
            return $url;
        }

        $url = trim($url, '/');

        return $url === '' ? 'dashboard' : $url;
    }

    private function _subnavHandle(array $childItem, string $parentKey): string
    {
        $url = (string)($childItem['url'] ?? '');
        $parentPath = NodeKey::split($parentKey)[1] ?? '';
        $prefix = $parentPath === '' ? '' : $parentPath . '/';

        if ($prefix !== '' && str_starts_with($url, $prefix)) {
            return StringHelper::toKebabCase(substr($url, strlen($prefix)));
        }

        return StringHelper::toKebabCase(basename($url));
    }

    /**
     * Resolve a stored customIcon JSON blob (`[assetId]`) to a filesystem path or URL Craft can SVG-render.
     */
    private function _customIconPath(?string $customIcon): ?string
    {
        return CustomIcon::resolveSvgSource($customIcon);
    }

    /**
     * Map stored icon values onto Craft's nav item keys.
     *
     * Modern Craft nav uses bare system SVG names (`gauge`, `graphql`) on `icon`.
     * Only an explicit `fontIcon:` prefix (migrated / stored override) maps to `fontIcon`.
     */
    private function _applyIcon(array &$item, string $icon, bool $isCustomSvg): void
    {
        if ($isCustomSvg) {
            $item['icon'] = $icon;

            return;
        }

        if ($icon === 'title') {
            // Craft renders the first letter of the label when no icon is set.
            return;
        }

        if (str_starts_with($icon, 'fontIcon:')) {
            $item['fontIcon'] = substr($icon, strlen('fontIcon:'));

            return;
        }

        // Bare names, paths, and @aliases are Craft SVG `icon` values — not font ligatures.
        $item['icon'] = $icon;
    }
}
