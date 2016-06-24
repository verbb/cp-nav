<?php
namespace Craft;

class CpNavService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function getPlugin()
    {
        return craft()->plugins->getPlugin('CpNav');
    }

    public function getSettings()
    {
        return $this->getPlugin()->getSettings();
    }

    //
    // Main hook for modifying control panel navigation
    //

    public function modifyCpNav(&$nav)
    {
        $layout = craft()->cpNav_layout->getByUserId();

        // If we're passing in a layoutId param, we're likely on the CP Nav settings page
        // so we want to force the particular layout we're on to the selected one
        if (craft()->request->getParam('layoutId')) {
            $layout = craft()->cpNav_layout->getById(craft()->request->getParam('layoutId'));
        }

        // Its pretty annoying, but each load of the CP, we need to check if the stored
        // menu items are different to the generated ones. Make sure this is lightweight!
        $allNavs = craft()->cpNav_nav->getByLayoutId($layout->id, 'handle');

        // No nav items? Create them now
        if ($allNavs) {

            // Get all records that are not manually created by user - easy way to check for changes
            $manualNavs = craft()->cpNav_nav->getAllManual($layout->id, 'handle');

            // Something has changed - either added or deleted. Re-generate the menu
            if (count($nav) != count($manualNavs)) {
                $this->regenerateNav($layout->id, $manualNavs, $nav);

                // We've either deleted/removed an element = fetch again
                $allNavs = craft()->cpNav_nav->getByLayoutId($layout->id, 'handle');
            }

            // Re-create the nav in our user-defined order
            $nav = array();

            foreach ($allNavs as $newNav) {

                // Allow links to be opened in new window - insert some small JS
                if ($newNav->newWindow) {
                    $this->_insertJsForNewWindow($newNav);
                }

                // Do some extra work on the url if needed
                $url = $this->_processUrl($newNav);

                if ($newNav->enabled) {
                    $nav[$newNav->handle] = array(
                        'label' => Craft::t($newNav->currLabel),
                        'url' => $url,
                    );

                    // Check for placeholder icons - we need to fetch from the plugin
                    if ($newNav->pluginIcon) {
                        $nav[$newNav->handle]['iconSvg'] = $newNav->pluginIcon;
                    }

                    if ($newNav->craftIcon) {
                        $nav[$newNav->handle]['icon'] = $newNav->icon;
                    }

                    if ($newNav->customIcon) {
                        try {
                            $asset = craft()->assets->getFileById($newNav->customIcon);
                            $path = $asset->getSource()->settings['path'] . $asset->getFolder()->path . $asset->filename;

                            if (IOHelper::fileExists($path)) {
                                $iconSvg = IOHelper::getFileContents($path);
                            } else {
                                $iconSvg = false;
                            }

                            $nav[$newNav->handle]['iconSvg'] = $iconSvg;
                        } catch (\Exception $e) {}
                    }
                }
            }
        }
    }

    //
    // Initial seed data - setup default layout, add in current cp nav
    //

    public function setupDefaults($layoutId = 1)
    {
        // Create the default Layout after plugin is installed
        if (!craft()->cpNav_layout->getById($layoutId)) {
            $layout = new CpNav_LayoutRecord();

            $layout->id = $layoutId;
            $layout->name = 'Default';
            $layout->isDefault = true;
            $layout->save();
        }

        // Populate navs with 'stock' navigation
        $defaultNavs = new CpVariable();

        $order = 0;
        foreach ($defaultNavs->nav() as $key => $nav) {
            if (!craft()->cpNav_nav->getByHandle($layoutId, $key)) {

                // Handleball off to the main menu regeneration function - no need to duplicate code
                $this->regenerateNav($layoutId, null, $defaultNavs->nav());
            }

            $order++;
        }
    }

    //
    // Creates or deletes records when the menu is updated by plugins
    //

    public function regenerateNav($layoutId, $generatedNav, $currentNav)
    {
        // Find the extra or missing menu item
        if (count($generatedNav) < count($currentNav)) {
            $order = 0;

            // A menu item exists in the menu, but not in our records - add
            foreach ($currentNav as $key => $value) {
                if (!isset($generatedNav[$key])) {

                    if (isset($value['url'])) {
                        // Some cases we call CpVariable directly, which contains the full url - strip that out
                        $url = str_replace(UrlHelper::getUrl() . '/', '', $value['url']);
                    } else {
                        $url = $key;
                    }

                    // Get the icon class if core, for plugins, we store a placeholder (iconSvg-pluginHandle), and fetch
                    // the plugin icon later because we don't really want to store the actual SVG icon in our db...
                    if (isset($value['icon'])) {
                        $icon = $value['icon'];
                    } else if (isset($value['iconSvg'])) {
                        $icon = 'iconSvg-' . $key;
                    } else {
                        $icon = '';
                    }

                    $model = $this->_prepareNavModel(array(
                        'layoutId' => $layoutId,
                        'handle' => $key,
                        'label' => $value['label'],
                        'order' => $order,
                        'icon' => $icon,
                        'url' => $url,
                    ));

                    craft()->cpNav_nav->save($model);
                }

                $order++;
            }
        } else {

            // A menu item exists in our records, but not in the menu - delete
            foreach ($generatedNav as $key => $value) {
                if (!isset($currentNav[$value['handle']])) {

                    $navModel = craft()->cpNav_nav->getByHandle($layoutId, $value['handle']);

                    craft()->cpNav_nav->delete($navModel);
                }
            }
        }
    }


    // Private Methods
    // =========================================================================

    private function _prepareNavModel($attributes)
    {
        $model = new CpNav_NavModel();

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

    private function _processUrl($newNav)
    {
        // Allow Enviroment Variables to be used in the URL
        $url = craft()->config->parseEnvironmentString(trim($newNav->url));

        // Support siteUrl
        $url = str_replace('{siteUrl}', craft()->getSiteUrl() . '/', $url);

        // And a spcial case for global - always direct to first global set
        if ($newNav->handle == 'globals') {
            $globals = craft()->globals->getEditableSets();

            if ($globals) {
                $url = 'globals/' . $globals[0]->handle;
            }
        }

        return UrlHelper::getUrl($url);
    }

    private function _insertJsForNewWindow($nav)
    {
        // Prevent this from loading when opening a modal window
        if (!craft()->request->isAjaxRequest()) {
            $navElement = '#global-sidebar #nav li#nav-' . $nav->handle . ' a';
            $js = '$(function() { $("'.$navElement.'").attr("target", "_blank"); });';
            craft()->templates->includeJs($js);
        }
    }

}
