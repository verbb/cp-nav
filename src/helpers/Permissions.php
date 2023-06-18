<?php
namespace verbb\cpnav\helpers;

use verbb\cpnav\CpNav;

use Craft;
use craft\base\UtilityInterface;
use craft\elements\User;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;

use Throwable;

class Permissions
{
    // Public Methods
    // =========================================================================

    public static function getBaseNavItems(): array
    {
        $craftPro = Craft::$app->getEdition() === Craft::Pro;
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        $navItems = [
            [
                'label' => Craft::t('app', 'Dashboard'),
                'url' => 'dashboard',
                'fontIcon' => 'gauge',
                'type' => 'craft',
            ],
        ];

        if (Craft::$app->getSections()->getAllSections()) {
            $navItems[] = [
                'label' => Craft::t('app', 'Entries'),
                'url' => 'entries',
                'fontIcon' => 'section',
                'type' => 'craft',
            ];
        }

        if (Craft::$app->getGlobals()->getAllSets()) {
            $navItems[] = [
                'label' => Craft::t('app', 'Globals'),
                'url' => 'globals',
                'fontIcon' => 'globe',
                'type' => 'craft',
            ];
        }

        if (Craft::$app->getCategories()->getAllGroups()) {
            $navItems[] = [
                'label' => Craft::t('app', 'Categories'),
                'url' => 'categories',
                'fontIcon' => 'tree',
                'type' => 'craft',
            ];
        }

        if (Craft::$app->getVolumes()->getAllVolumes()) {
            $navItems[] = [
                'label' => Craft::t('app', 'Assets'),
                'url' => 'assets',
                'fontIcon' => 'asset',
                'type' => 'craft',
            ];
        }

        if ($craftPro) {
            $navItems[] = [
                'label' => Craft::t('app', 'Users'),
                'url' => 'users',
                'fontIcon' => 'users',
                'type' => 'craft',
            ];
        }

        // Add any Plugin nav items
        foreach (Craft::$app->getPlugins()->getAllPlugins() as $plugin) {
            try {
                if ($plugin->hasCpSection && ($pluginNavItem = $plugin->getCpNavItem()) !== null) {
                    $pluginNavItem['type'] = 'plugin';

                    $navItems[] = $pluginNavItem;
                }
            } catch (Throwable $e) {
                // Log the error, but continue
                CpNav::error('Error fetching navigation item for {plugin}: {e}', ['plugin' => $plugin->handle, 'e' => $e->getMessage()]);

                continue;
            }
        }

        // Call the original `EVENT_REGISTER_CP_NAV_ITEMS` event, in case some plugin register nav items in an event.
        $event = new RegisterCpNavItemsEvent([
            'navItems' => $navItems,
        ]);
        (new Cp())->trigger(Cp::EVENT_REGISTER_CP_NAV_ITEMS, $event);

        // Only deal with some menu items
        foreach ($event->navItems as $key => $navItem) {
            if (!is_numeric($key)) {
                $navItems[] = $navItem;
            }
        }

        if ($craftPro && $generalConfig->enableGql) {
            $subNavItems = [];

            if ($generalConfig->allowAdminChanges) {
                $subNavItems['schemas'] = [
                    'label' => Craft::t('app', 'Schemas'),
                    'url' => 'graphql/schemas',
                ];
            }

            $subNavItems['tokens'] = [
                'label' => Craft::t('app', 'Tokens'),
                'url' => 'graphql/tokens',
            ];

            $subNavItems['graphiql'] = [
                'label' => 'GraphiQL',
                'url' => 'graphiql',
                'external' => true,
            ];

            $navItems[] = [
                'label' => Craft::t('app', 'GraphQL'),
                'url' => 'graphql',
                'icon' => '@appicons/graphql.svg',
                'type' => 'craft',
                'subnav' => $subNavItems,
            ];
        }

        $utilities = Craft::$app->getUtilities()->getAllUtilityTypes();

        if (!empty($utilities)) {
            $badgeCount = 0;

            foreach ($utilities as $class) {
                /** @var UtilityInterface $class */
                $badgeCount += $class::badgeCount();
            }

            $navItems[] = [
                'url' => 'utilities',
                'label' => Craft::t('app', 'Utilities'),
                'fontIcon' => 'tool',
                'type' => 'craft',
                'badgeCount' => $badgeCount,
            ];
        }

        if ($generalConfig->allowAdminChanges) {
            $navItems[] = [
                'url' => 'settings',
                'label' => Craft::t('app', 'Settings'),
                'fontIcon' => 'settings',
                'type' => 'craft',
            ];
        }

        $navItems[] = [
            'url' => 'plugin-store',
            'label' => Craft::t('app', 'Plugin Store'),
            'fontIcon' => 'plugin',
            'type' => 'craft',
        ];

        return $navItems;
    }

    public static function getPermissionMap(): array
    {
        $craftPro = Craft::$app->getEdition() === Craft::Pro;
        $isAdmin = Craft::$app->getUser()->getIsAdmin();
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        // Prepare a key-may of permission-handling
        $permissionMap = [
            'entries' => (bool)Craft::$app->getSections()->getTotalEditableSections(),
            'globals' => (bool)Craft::$app->getGlobals()->getEditableSets(),
            'categories' => (bool)Craft::$app->getCategories()->getEditableGroupIds(),
            'assets' => (bool)Craft::$app->getVolumes()->getTotalViewableVolumes(),
            'users' => $craftPro && Craft::$app->getUser()->checkPermission('editUsers'),

            'utilities' => (bool)Craft::$app->getUtilities()->getAuthorizedUtilityTypes(),

            'graphql' => $isAdmin && $craftPro && $generalConfig->enableGql,
            'settings' => $isAdmin && $generalConfig->allowAdminChanges,
            'plugin-store' => $isAdmin,
        ];

        // Add each plugin
        foreach (Craft::$app->getPlugins()->getAllPlugins() as $plugin) {
            if ($pluginNavItem = $plugin->getCpNavItem()) {
                $permissionMap[$pluginNavItem['url']] = Craft::$app->getUser()->checkPermission('accessPlugin-' . $plugin->id);
            }
        }

        return $permissionMap;
    }

    public static function checkPluginSubnavPermission($navigation): bool
    {
        $pluginHandle = explode('/', $navigation->url)[0] ?? null;
        
        if ($pluginHandle) {
            $plugin = Craft::$app->getPlugins()->getPlugin($pluginHandle);

            if ($plugin && $pluginNavItem = $plugin->getCpNavItem()) {
                // Chcek if the plugin has any subnav items
                if (is_array($pluginNavItem) && array_key_exists('subnav', $pluginNavItem)) {
                    $subNavItems = $pluginNavItem['subnav'] ?? [];

                    return isset($subNavItems[$navigation->handle]);
                }
            }
        }

        return true;
    }
}
