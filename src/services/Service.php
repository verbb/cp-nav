<?php
namespace verbb\cpnav\services;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Navigation as NavigationModel;

use Craft;
use craft\base\Component;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\web\twig\variables\Cp;

class Service extends Component
{
    // Public Methods
    // =========================================================================

    public function modifyCpNav(&$nav)
    {
        // Keep a temporary copy of the un-altered nav in case things go wrong
        $originalNav = $nav;
        $subNavs = [];
        $badges = [];

        // Save any sub-navs for plugins for later, just index them by the plugin handle
        foreach ($originalNav as $value) {
            if (isset($value['subnav'])) {
                $subNavs[$value['url']] = $value['subnav'];
            }

            if (isset($value['badgeCount'])) {
              $badges[$value['url']] = $value['badgeCount'];
            }
        }

        try {
            $layout = CpNav::$plugin->getLayouts()->getLayoutByUserId();

            // If we're passing in a layoutId param, we're likely on the CP Nav settings page
            // so we want to force the particular layout we're on to the selected one
            $editing = false;
            $layoutId = Craft::$app->getRequest()->getParam('layoutId');

            if ($layoutId) {
                $layout = CpNav::$plugin->getLayouts()->getLayoutById($layoutId);
                $editing = true;
            }

            // Its pretty annoying, but each load of the CP, we need to check if the stored
            // menu items are different to the generated ones. Make sure this is lightweight!
            $allNavs = CpNav::$plugin->getNavigations()->getNavigationsByLayoutId($layout->id, 'handle');

            // No nav items? Create them now
            if ($allNavs) {

                // Get all records that are not manually created by user - easy way to check for changes
                $manualNavs = CpNav::$plugin->getNavigations()->getAllManualNavigations($layout->id, 'handle');

                // Something has changed - either added or deleted. Re-generate the menu
                if ((count($nav) != count($manualNavs)) && !$editing) {
                    $this->regenerateNav($layout->id, $manualNavs, $nav);

                    // We've either deleted/removed an element = fetch again
                    $allNavs = CpNav::$plugin->getNavigations()->getNavigationsByLayoutId($layout->id, 'handle');
                }

                // Re-create the nav in our user-defined order
                $nav = [];

                /** @var NavigationModel $newNav */
                foreach ($allNavs as &$newNav) {

                    // Allow links to be opened in new window - insert some small JS
                    if ($newNav->newWindow) {
                        $this->_insertJsForNewWindow($newNav);
                    }

                    if ($newNav->enabled) {
                        $nav[$newNav->handle] = [
                            'id'    => 'nav-' . $newNav->handle,
                            'label' => Craft::t('app', $newNav->currLabel),
                            'url'   => $newNav->parsedUrl,
                        ];

                        // Check for placeholder icons - we need to fetch from the plugin
                        if ($newNav->craftIcon) {
                            $nav[$newNav->handle]['fontIcon'] = $newNav->icon;
                        }

                        if ($newNav->pluginIcon) {
                            $nav[$newNav->handle]['icon'] = $newNav->pluginIcon;
                        }

                        // Check for plugin sub-navs
                        if (isset($subNavs[$newNav->handle])) {
                            $nav[$newNav->handle]['subnav'] = $subNavs[$newNav->handle];
                        }

                        // Check for badges
                        if (isset($badges[$newNav->handle])) {
                            $nav[$newNav->handle]['badgeCount'] = $badges[$newNav->handle];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Something went wrong! Restore the original nav
            $nav = $originalNav;

            Craft::error(Craft::t('cp-nav', $e->getMessage()), __METHOD__);
        }
    }

    public function setupDefaults($layoutId = 1)
    {
        $layoutService = CpNav::$plugin->getLayouts();
        $navigationService = CpNav::$plugin->getNavigations();

        if (!$layoutService->getLayoutById($layoutId)) {
            $layoutService->setDefaultLayout($layoutId);
        }

        // Populate navs with 'stock' navigation
        $navService = new Cp();
        $defaultNavs = $navService->nav();

        foreach ($defaultNavs as $nav) {
            $key = strtolower($nav['label']);

            if (!$navigationService->getNavigationByHandle($layoutId, $key)) {

                // Handball off to the main menu regeneration function - no need to duplicate code
                $this->regenerateNav($layoutId, [], $defaultNavs);
            }
        }
    }

    public function regenerateNav($layoutId, $generatedNav, $currentNav)
    {
        // Find the extra or missing menu item
        if (count($generatedNav) < count($currentNav)) {
            $order = 0;

            // A menu item exists in the menu, but not in our records - add
            foreach ($currentNav as $value) {
                if (isset($value['url'])) {
                    $handle = str_replace(UrlHelper::url() . '/', '', $value['url']);
                } else {
                    $handle = StringHelper::toKebabCase($value['label']);
                }

                if (!isset($generatedNav[$handle]) && !CpNav::$plugin->getNavigations()->getNavigationByHandle($layoutId, $handle)) {
                    $icon = null;

                    // Check for custom icon (plugins)
                    if (isset($value['icon'])) {
                        $icon = $value['icon'];
                    }

                    // Check for built-in Craft icon
                    if (isset($value['fontIcon'])) {
                        $icon = $value['fontIcon'];
                    }

                    $model = $this->_prepareNavModel([
                        'layoutId' => $layoutId,
                        'handle'   => $handle,
                        'label'    => $value['label'],
                        'order'    => $order,
                        'icon'     => $icon,
                        'url'      => $handle,
                    ]);

                    CpNav::$plugin->getNavigations()->saveNavigation($model);
                }

                $order++;
            }
        } else {
            // Create an array of current navigation handles to easy check via in_array
            $currentNavHandles = array_column($currentNav, 'url');

            // A menu item exists in our records, but not in the menu - delete
            foreach ($generatedNav as $key => $value) {
                if (!\in_array($value['handle'], $currentNavHandles, false)) {
                    $navModel = CpNav::$plugin->getNavigations()->getNavigationByHandle($layoutId, $value['handle']);

                    if ($navModel) {
                        CpNav::$plugin->getNavigations()->deleteNavigation($navModel);
                    }
                }
            }
        }
    }


    // Private Methods
    // =========================================================================

    private function _prepareNavModel($attributes): NavigationModel
    {
        $model = new NavigationModel();

        $model->layoutId = $attributes['layoutId'];
        $model->handle = $attributes['handle'];
        $model->currLabel = $attributes['label'];
        $model->prevLabel = $attributes['label'];
        $model->enabled = true;
        $model->order = $attributes['order'];
        $model->url = $attributes['url'];
        $model->prevUrl = $attributes['url'];
        $model->icon = $attributes['icon'];
        $model->manualNav = false;
        $model->newWindow = false;

        return $model;
    }

    private function _insertJsForNewWindow(NavigationModel $nav)
    {
        // Prevent this from loading when opening a modal window
        if (!Craft::$app->getRequest()->isAjax) {
            $navElement = '#global-sidebar #nav li#nav-' . $nav->handle . ' a';
            $js = '$(function() { $("' . $navElement . '").attr("target", "_blank"); });';
            
            Craft::$app->view->registerJs($js);
        }
    }
}
