<?php
namespace verbb\cpnav\services;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Layout as LayoutModel;
use verbb\cpnav\models\Navigation as NavigationModel;

use Craft;
use craft\base\Component;
use craft\events\PluginEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\web\twig\variables\Cp;

use yii\web\UserEvent;

class Service extends Component
{
    // Properties
    // =========================================================================

    private $_originalNavItems = [];
    private $_subNavs = [];
    private $_badges = [];


    // Public Methods
    // =========================================================================

    public function generateNavigation($event)
    {
        $this->_originalNavItems = $event->navItems;

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

    public function checkUpdatedNavItems($event)
    {
        try {
            $generalConfig = Craft::$app->getConfig()->getGeneral();

            // Don't proceed if admin changes are disallowed
            if (!$generalConfig->allowAdminChanges) {
                return;
            }

            $currentHash = $this->_encodeHash($event->navItems);
            $originalNavHash = $this->_getOriginalNavHash();

            // If there's no saved record of the original nav, store it
            if (!$originalNavHash) {
                $this->_saveHash($currentHash);
            }

            // Check to see if something has changed
            if ($originalNavHash !== $currentHash) {
                $changedHash = false;
                $oldNavItems = $this->_decodeHash($originalNavHash) ?? [];
                $newNavItems = $event->navItems ?? [];

                if (!is_array($oldNavItems)) {
                    $oldNavItems = [];
                }

                if (!is_array($newNavItems)) {
                    $newNavItems = [];
                }

                // Let's find out what's changed! Are the new navs bigger than the old - we've added
                if (count($oldNavItems) < count($newNavItems)) {
                    // A new nav has been added, find it
                    $result = $this->_findMissingItem($newNavItems, $oldNavItems);

                    if ($result) {
                        CpNav::$plugin->getPendingNavigations()->set($result);

                        $changedHash = true;
                    }
                } else {
                    // A node has been removed
                    $result = $this->_findMissingItem($oldNavItems, $newNavItems);

                    if ($result) {
                        $handle = $result['url'] ?? '';
                        
                        CpNav::$plugin->getNavigations()->deleteNavigationFromAllLayouts($handle);

                        $changedHash = true;
                    }
                }

                if ($changedHash) {
                    $this->_saveHash($currentHash);
                }
            }
        } catch (\Throwable $e) {
            CpNav::error(Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]));
        }
    }

    public function processPendingNavItems($event)
    {
        // Check to see if we've installed any plugins that have updates for us to apply. We have to use the DB 
        // to store these (as opposed to sessions) so we can support installing plugins via the console
        // (where sessions aren't supported and throw an error)
        $pluginNavItems = CpNav::$plugin->getPendingNavigations()->get();

        foreach ($pluginNavItems as $pluginNavItem) {
            $errors = [];

            try {
                $navigation = $this->_createNavigationModelForNavItem($pluginNavItem);

                // Just add to the end of the list for now, too tricky to sort out otherwise
                $navigation->order = 9999;

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

        foreach ($originalNavItems as $index => $originalNavItem) {
            $navigation = $this->_createNavigationModelForNavItem($originalNavItem);
            $navigation->layoutId = $layoutId;
            $navigation->order = $index;

            CpNav::$plugin->getNavigations()->saveNavigation($navigation);
        }
    }


    // Private Methods
    // =========================================================================

    private function _getOriginalNav()
    {
        // Allow CpNav services to be called by console requests
        // https://github.com/verbb/cp-nav/issues/85
        if(!Craft::$app->getRequest()->getIsConsoleRequest()) {

            // Just call it - we don't want the result of this function, we just want the hook called,
            // which in turn calls our function above. Our hook will store the original nav in a private
            // variable, for final use here. Might be a better way?
            (new Cp())->nav();
        }

        return $this->_originalNavItems;
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

    private function _findMissingItem($array1, $array2)
    {
        $result = [];

        foreach ($array1 as $key => $value) {
            $oldIndex = $array2[$key]['url'] ?? '';

            if ($value['url'] != $oldIndex) {
                $result = $value;

                break;
            }
        }

        return $result;
    }

    private function _encodeHash($object)
    {
        return base64_encode(Json::encode($object));
    }

    private function _decodeHash($object)
    {
        return Json::decode(base64_decode($object));
    }

    private function _getOriginalNavHash()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $settings = CpNav::$plugin->getSettings();

        return $settings->originalNavHash[$currentUser->uid] ?? '';
    }

    private function _saveHash($hash)
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $settings = CpNav::$plugin->getSettings();

        $settings->originalNavHash[$currentUser->uid] = $hash;

        $plugin = Craft::$app->getPlugins()->getPlugin('cp-nav');

        Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray());
    }
}
