<?php
namespace verbb\cpnav\helpers;

use Craft;
use craft\base\UtilityInterface;

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
            if ($plugin->hasCpSection && ($pluginNavItem = $plugin->getCpNavItem()) !== null) {
                $pluginNavItem['type'] = 'plugin';

                $navItems[] = $pluginNavItem;
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
}
