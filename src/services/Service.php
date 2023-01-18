<?php
namespace verbb\cpnav\services;

use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\Permissions;
use verbb\cpnav\models\Navigation;

use Craft;
use craft\base\Component;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\web\View;

use yii\base\Application;

use Throwable;

class Service extends Component
{
    // Public Methods
    // =========================================================================

    public function getNavigationHtml(array $variables = []): ?string
    {
        try {
            // Add in our own variable to be used when rendering the template
            $variables['cpNavItems'] = $this->_getNavigationsForUser();

            if ($variables['cpNavItems']) {
                return Craft::$app->getView()->renderTemplate('cp-nav/_layouts/navs', $variables);
            }
        } catch (Throwable $e) {
            CpNav::error(Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]));
        }

        return null;
    }

    public function renderNavigation($context): void
    {
        // We need an authenticated session to proceed further
        if (!Craft::$app->getUser()->getIdentity()) {
            return;
        }

        try {
            $view = Craft::$app->getView();

            // Render the navigation for the current user.
            // Include global Twig context variables, so things like plugins setting their `selectedSubnavItem` works.
            if ($renderedHtml = $this->getNavigationHtml($context)) {
                $navHtml = Json::encode($renderedHtml, JSON_UNESCAPED_UNICODE);

                // Hide the normal sidebar immediately, to prevent a flicker (although it'll barely be noticeable
                // due to `MutationObserver` being so quick)
                $css = '#global-sidebar #nav:not(.cp-nav-menu) { display: none; }';
                $view->registerCss($css);

                // Use MutationObserver to watch when the `#global-sidebar #nav` become available to replace.
                // It's crazy efficient and quick, and better than waiting for jQuery to kick in. It also
                // allows us to render this at the start of the document for speedy replacement rather than
                // wait until the end of the document, or when jQuery and everything else has had its way with the DOM.
                $js = <<<JS
function waitForElm(selector) {
    return new Promise(resolve => {
        if (document.querySelector(selector)) {
            return resolve(document.querySelector(selector));
        }

        const observer = new MutationObserver(mutations => {
            if (document.querySelector(selector)) {
                resolve(document.querySelector(selector));
                observer.disconnect();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
}

waitForElm("#global-sidebar #nav").then((sidebarNav) => {
    sidebarNav.innerHTML = {$navHtml};
    sidebarNav.classList.add("cp-nav-menu");
});
JS;
                $view->registerJs($js, View::POS_BEGIN);
            }
        } catch (Throwable $e) {
            CpNav::error(Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]));
        }
    }

    public function resetLayout($layoutId): void
    {
        $navigationService = CpNav::$plugin->getNavigations();
        $navigations = $navigationService->getAllNavigationsByLayoutId($layoutId);

        foreach ($navigations as $navigation) {
            $navigationService->deleteNavigation($navigation);
        }

        $this->_createNavigationForNavItems($layoutId, Permissions::getBaseNavItems());
    }


    // Private Methods
    // =========================================================================

    private function _getNavigationsForUser(): array
    {
        $navigations = [];

        // Get the navigation items for this layout
        if ($layout = CpNav::$plugin->getLayouts()->getLayoutForCurrentUser()) {
            // Get the "native" menu items first, so we have something to compare. They're not truly native
            // because the `craft\web\twig\variables\Cp::nav()` function wraps everything with permissions,
            // and we want to do that **after** comparing with our saved nav items. Otherwise, we end up with
            // different rendered nav items depending on the user permissions.
            $originalNavItems = Permissions::getBaseNavItems();

            // Now that we have our base nav items, check to see if there's a mismatch. Namely:
            // 1. Craft has a new core menu (Sections/Category Groups/Volumes have been added)
            // 2. A plugin has been installed/uninstalled
            // 3. A plugin has been disabled via `disabledPlugins`
            // 4. A module has registered `getCpNavItem()`
            // This will **not** check for permissions - only if they are globally available/unavailable
            /// Navigation items will be added or deleted depending on the matching status.
            $this->_checkUpdatedNavItems($originalNavItems, $layout->getNavigations());

            // Generate a permission map, so we can check against it in each nav item.
            $permissionMap = Permissions::getPermissionMap();

            foreach ($layout->getNavigations() as $navigation) {
                // Despite having a custom, set menu for all users, we still need to check permissions
                // based on the current users' permission level. We wouldn't want to show a plugin nav item
                // if the user doesn't have access to it (even if defined in CP Nav).
                $permission = $permissionMap[$navigation->handle] ?? null;

                // Ignore anything not top-level. Those are turned into a subnav
                if ($permission === false || !$navigation->enabled || $navigation->level !== 1) {
                    continue;
                }

                // Get the current nav item, as we'll need to pluck some values out
                $navigation->setOriginalNavItem(ArrayHelper::firstWhere($originalNavItems, 'url', $navigation->handle));

                $navigations[] = $navigation;
            }
        }

        return $navigations;
    }

    private function _checkUpdatedNavItems($originalNavItems, $newNavItems): void
    {
        try {
            $generalConfig = Craft::$app->getConfig()->getGeneral();

            // Don't proceed if admin changes are disallowed
            if (!$generalConfig->allowAdminChanges) {
                return;
            }

            $hasChanged = false;

            // Compare using `prevUrl` instead of handle, just in case there are duplicate handles
            // And in particular, `settings` will be common enough to cause issues across Craft and plugins.
            $navItems = ArrayHelper::index($originalNavItems, 'url');
            $navigations = ArrayHelper::index($newNavItems, 'prevUrl');

            $layoutsService = CpNav::$plugin->getLayouts();
            $navigationService = CpNav::$plugin->getNavigations();

            // Are there any items in the old nav that aren't in the new one? We need to add it.
            foreach ($navItems as $handle => $navItem) {
                if (!isset($navigations[$handle])) {
                    $layouts = $layoutsService->getAllLayouts();

                    // Create the new nav item(s) for all layouts
                    foreach ($layouts as $layout) {
                        // Ensure that it doesn't already exist for this layout - just in case.
                        if (ArrayHelper::firstWhere($layout->getNavigations(), 'prevUrl', $handle)) {
                            continue;
                        }

                        $this->_createNavigationForNavItems($layout->id, [$navItem]);
                    }

                    $hasChanged = true;
                }
            }

            // Are there any items in the new nav that aren't in the old one? Ignore divider ot manual of course.
            // This will cover anything that uses `getCpNavItem()` (plugins and modules) and Craft itself.
            foreach ($navigations as $handle => $navigation) {
                if (!isset($navItems[$handle])) {
                    // Also check if this was originally a subnav, and moved top-level - skip it
                    if (!in_array($navigation->type, ['divider', 'manual']) && !$navigation->isSubnav(true)) {
                        // Delete the new nav, as the plugin (or Craft page) is no longer registered
                        $navigationService->deleteNavigationFromAllLayouts($handle);

                        $hasChanged = true;
                    }
                }
            }

            // For bulk updates, the Project config doesn't seem to kick in unless we tell it to. Maybe a bug in PC?
            // Or maybe due to the fact it only regenerates the external config at the end of a request.
            if ($hasChanged) {
                // Trigger the end of a 'request'. This lets project config do its stuff.
                // TODO: Probably Craft::$app->getProjectConfig->saveModifiedConfigData() but I feel the below is more solid.
                Craft::$app->state = Application::STATE_END;
                Craft::$app->trigger(Application::EVENT_AFTER_REQUEST);
            }
        } catch (Throwable $e) {
            CpNav::error(Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]));
        }
    }

    private function _createNavigationForNavItems($layoutId, array $navItems): void
    {
        $navigationService = CpNav::$plugin->getNavigations();

        foreach ($navItems as $navItem) {
            $navigation = new Navigation();
            $navigation->handle = $navItem['url'] ?? '';
            $navigation->currLabel = $navItem['label'] ?? '';
            $navigation->prevLabel = $navItem['label'] ?? '';
            $navigation->enabled = true;
            $navigation->url = $navItem['url'] ?? '';
            $navigation->prevUrl = $navItem['url'] ?? '';
            $navigation->icon = $navItem['icon'] ?? $navItem['fontIcon'] ?? '';
            $navigation->type = $navItem['type'] ?? '';
            $navigation->newWindow = $navItem['external'] ?? false;
            $navigation->layoutId = $layoutId;
            $navigation->prevLevel = 1;
            $navigation->level = 1;

            $navigationService->saveNavigation($navigation);

            // Also do the same thing with subnav items
            $subnavs = $navItem['subnav'] ?? [];

            if ($subnavs) {
                foreach ($subnavs as $handle => $subnav) {
                    $subNavigation = new Navigation();
                    $subNavigation->handle = $handle;
                    $subNavigation->currLabel = $subnav['label'] ?? '';
                    $subNavigation->prevLabel = $subnav['label'] ?? '';
                    $subNavigation->enabled = true;
                    $subNavigation->url = $subnav['url'] ?? '';
                    $subNavigation->prevUrl = $subnav['url'] ?? '';
                    $subNavigation->type = $navigation->type;
                    $subNavigation->newWindow = $subnav['external'] ?? false;
                    $subNavigation->layoutId = $layoutId;
                    $subNavigation->prevLevel = 2;
                    $subNavigation->level = 2;
                    $subNavigation->prevParentId = $navigation->id;
                    $subNavigation->parentId = $navigation->id;

                    $navigationService->saveNavigation($subNavigation);
                }
            }
        }
    }
}
