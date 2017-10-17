<?php
namespace verbb\cpnav\services;

use verbb\cpnav\CpNav as CpNavPlugin;
use verbb\cpnav\models\Navigation as NavigationModel;

use Craft;
use craft\base\Component;
use craft\helpers\UrlHelper;
use craft\web\twig\variables\Cp;

class CpNav extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Create the default Layout after plugin is installed
     *
     * @param int $layoutId
     */
    public function setupDefaults($layoutId = 1)
    {
//        if (!craft()->cpNav_layout->getById($layoutId)) {
        if (!CpNavPlugin::$plugin->layoutService->getById($layoutId)) {
            CpNavPlugin::$plugin->layoutService->setDefaultLayout($layoutId);
        }

        // Populate navs with 'stock' navigation
        $defaultNavs = new Cp();

        $order = 0;
        foreach ($defaultNavs->nav() as $key => $nav) {
//            if (!craft()->cpNav_nav->getByHandle($layoutId, $key)) {
            if (!CpNavPlugin::$plugin->navigation->getByHandle($layoutId, $key)) {

                // Handleball off to the main menu regeneration function - no need to duplicate code
                $this->regenerateNav($layoutId, null, $defaultNavs->nav());
            }

            $order++;
        }
    }

    /**
     * Creates or deletes records when the menu is updated by plugins
     *
     * @param $layoutId
     * @param $generatedNav
     * @param $currentNav
     */
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
                        $url = str_replace(UrlHelper::url() . '/', '', $value['url']);
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

//                    craft()->cpNav_nav->save($model);
                    CpNavPlugin::$plugin->navigation->save($model);
                }

                $order++;
            }
        } else {

            // A menu item exists in our records, but not in the menu - delete
            foreach ($generatedNav as $key => $value) {
                if (!isset($currentNav[$value['handle']])) {

//                    $navModel = craft()->cpNav_nav->getByHandle($layoutId, $value['handle']);
                    $navModel = CpNavPlugin::$plugin->navigation->getByHandle($layoutId, $value['handle']);

//                    craft()->cpNav_nav->delete($navModel);
                    CpNavPlugin::$plugin->navigation->delete($navModel);
                }
            }
        }
    }


    // Private Methods
    // =========================================================================

    private function _prepareNavModel($attributes)
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
}
