<?php
namespace verbb\cpnav\services;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Layout as LayoutModel;
use verbb\cpnav\models\Navigation as NavigationModel;

use Craft;
use craft\base\Component;
use craft\events\PluginEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\web\twig\variables\Cp;

use yii\web\UserEvent;

class Service extends Component
{
    // Properties
    // =========================================================================

    private $_originalnavItems = [];
    private $_subNavs = [];
    private $_badges = [];

    // Public Methods
    // =========================================================================

    public function generateNavigation($event)
    {
        $this->_originalnavItems = $event->navItems;

        try {
            $newNavItems = [];

            // Save any sub-navs and badges, we need to apply these back onto modified navs
            $this->_saveSubNavsAndBadges($event->navItems);

            // Get the layout for the current user viewing the CP
            $layout = CpNav::$plugin->getLayouts()->getLayoutForCurrentUser();

            // Get the navigation items for this layout
            foreach ($layout->getNavigations() as $navigation) {
                $newNavItem = $navigation->generateNavItem();

                // Apply any previous subnavs or badges back onto navs items
                if ($newNavItem) {
                    $this->_applySubNavsAndBadges($newNavItem);

                    $newNavItems[] = $newNavItem;
                }
            }

            // Update the original nav
            if ($newNavItems) {
                $event->navItems = $newNavItems;
            }
        } catch (\Throwable $e) {
            CpNav::error(Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]));
        }
    }

    public function afterPluginInstall(PluginEvent $event)
    {
        try {
            $plugin = $event->plugin;

            // Add the plugin's nav item
            if ($plugin->hasCpSection && ($pluginNavItem = $plugin->getCpNavItem()) !== null) {
                // So this is a bit annoying. At this point, new plugin items are added at the bottom
                // of the nav, which probably has to do with how new plugins are stored in the internal cache
                // So - in order to get the correct order to insert, save the info for later, on the next page request
                //
                // It'd be nice if we could ditch this, but requires investigation into Craft core
                CpNav::$plugin->getPendingNavigations()->set($pluginNavItem);
            }
        } catch (\Throwable $e) {
            CpNav::error(Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]));
        }
    }

    public function processPendingPluginInstall($event)
    {
        // Check to see if we've installed any plugins that have updates for us to apply. We have to use the DB 
        // to store these (as opposed to sessions) so we can support installing plugins via the console
        // (where sessions aren't supported and throw an error)
        $pluginNavItems = CpNav::$plugin->getPendingNavigations()->get();

        foreach ($pluginNavItems as $pluginNavItem) {
            $errors = [];

            try {
                $navigation = $this->_createNavigationModelForNavItem($pluginNavItem);

                // Its a bit of effort, but the only real way to get the correct order of the new nav item
                // is to look at how its placed normally, and use that. Better than appending though.
                $navItems = $event->navItems;

                foreach ($navItems as $orderIndex => $navItem) {
                    if ($navItem['url'] === $pluginNavItem['url']) {
                        $navigation->order = $orderIndex;

                        break;
                    }
                }

                // Create nav item for all layouts
                CpNav::$plugin->getNavigations()->saveNavigationToAllLayouts($navigation);
            } catch (\Throwable $e) {
                $error = Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]);

                CpNav::error($error);
                $errors[] = $error;

                continue;
            }

            // Clear out all pending items, unless errors
            if (!$errors) {
                CpNav::$plugin->getPendingNavigations()->remove();
            }
        }
    }

    public function afterPluginUninstall(PluginEvent $event)
    {
        try {
            $plugin = $event->plugin;

            // Remove the plugin's nav item
            if ($plugin->hasCpSection && ($pluginNavItem = $plugin->getCpNavItem()) !== null) {
                $handle = $pluginNavItem['url'] ?? '';

                if ($handle) {
                    CpNav::$plugin->getNavigations()->deleteNavigationFromAllLayouts($handle);
                }
            }
        } catch (\Throwable $e) {
            CpNav::error(Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]));
        }
    }

    public function populateOriginalNavigationItems($layoutId = 1)
    {
        $layoutService = CpNav::$plugin->getLayouts();
        $navigationService = CpNav::$plugin->getNavigations();

        // Just on the off-chance there's no default layout
        if (!$layoutService->getLayoutById($layoutId)) {
            $layout = new LayoutModel();
            $layout->id = $layoutId;
            $layout->name = 'Default';
            $layout->isDefault = true;

            $layoutService->saveLayout($layout, true);
        }

        // Populate navs with 'stock' navigation
        $originalNavItems = $this->_getOriginalNav();

        foreach ($originalNavItems as $originalNavItem) {
            $navigation = $this->_createNavigationModelForNavItem($originalNavItem);
            $navigation->layoutId = $layoutId;

            CpNav::$plugin->getNavigations()->saveNavigation($navigation);
        }
    }


    // Private Methods
    // =========================================================================

    private function _getOriginalNav()
    {
        // Just call it - we don't want the result of this function, we just want the hook called,
        // which in turn calls our function above. Our hook will store the original nav in a private 
        // variable, for final use here. Might be a better way?
        (new Cp())->nav();

        return $this->_originalnavItems;
    }
    
    private function _createNavigationModelForNavItem($pluginNavItem)
    {
        $navigation = new NavigationModel();
        $navigation->handle = $pluginNavItem['url'] ?? '';
        $navigation->currLabel = $pluginNavItem['label'] ?? '';
        $navigation->prevLabel = $pluginNavItem['label'] ?? '';
        $navigation->enabled = true;
        $navigation->url = $pluginNavItem['url'] ?? '';
        $navigation->prevUrl = $pluginNavItem['url'] ?? '';
        $navigation->icon = $pluginNavItem['icon'] ?? $pluginNavItem['fontIcon'] ?? '';
        $navigation->type = '';
        $navigation->newWindow = false;

        return $navigation;
    }

    private function _saveSubNavsAndBadges($originalNav)
    {
        foreach ($originalNav as $value) {
            if (isset($value['subnav'])) {
                $this->_subNavs[$value['url']] = $value['subnav'];
            }

            if (isset($value['badgeCount'])) {
                $this->_badges[$value['url']] = $value['badgeCount'];
            }
        }
    }

    private function _applySubNavsAndBadges(&$newNavItem)
    {
        // Check for plugin sub-navs
        if (isset($this->_subNavs[$newNavItem['url']])) {
            $newNavItem['subnav'] = $this->_subNavs[$newNavItem['url']];
        }

        // Check for badges
        if (isset($this->_badges[$newNavItem['url']])) {
            $newNavItem['badgeCount'] = $this->_badges[$newNavItem['url']];
        }
    }
}
