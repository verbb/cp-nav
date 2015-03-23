<?php
namespace Craft;

class CpNavService extends BaseApplicationComponent
{
    // Triggered after we've installed the plugin, but there's no stored data yet - load up some defaults
    public function setupDefaults($navs) {
        // Create a new layout called 'Default'
        $defaultLayout = CpNav_LayoutRecord::model()->findById('1');

        if ($defaultLayout) {
            $layoutsRecord = $defaultLayout;
        } else {
            $layoutsRecord = new CpNav_LayoutRecord();
        }

        //$layoutsRecord->id = '1';
        $layoutsRecord->name = 'Default';
        $layoutsRecord->isDefault = '1';

        $layoutsRecord->save();

        // With this new layout in mind, populate the nav table with items from the default cp nav
        $i = 0;
        foreach ($navs as $key => $value) {
            $navRecord = new CpNav_NavRecord();

            $navRecord->layoutId = '1';
            $navRecord->handle = $key;
            $navRecord->currLabel = $value['label'];
            $navRecord->prevLabel = $value['label'];
            $navRecord->enabled = '1';
            $navRecord->order = $i;
            $navRecord->url = (array_key_exists('url', $value)) ? $value['url'] : $key;
            $navRecord->prevUrl = $navRecord->url;
            $navRecord->manualNav = '0';
            $navRecord->newWindow = '0';

            $navRecord->save();
            $i++;
        }
    }



    // Determines if there are any new CP menu items (from a plugin install or Craft)
    // And likewise determines if a plugin has been removed - no need to keep menu item.
    public function checkIfUpdateNeeded($allNavs, $navs) {
        $layoutId = '1';

        // We're actually looping through each layout in our system, but only returning the one we asked for!
        // That way, we can easily handle re-generating all layouts
        $allLayouts = craft()->cpNav_layout->getAllLayouts();
        foreach ($allLayouts as $layout) {

            // Firstly, a quick size check between the default nav and our copy (manual links not included)
            // will tell us if we need to look at whats been added or missing

            // Get all records that are not manually created by user
            $manualNav = CpNav_NavRecord::model()->findAll(array('condition' => 'layoutId = '.$layout->id.' AND (manualNav IS NULL OR manualNav <> 1)', 'index' => 'handle'));

            // If not equal, looks like something has changed!
            if (count($manualNav) != count($navs)) {
                if (count($manualNav) < count($navs)) {
                    // There are new menu items that have been added

                    $i = 0;
                    foreach ($navs as $key => $value) {
                        if (!array_key_exists($key, $manualNav)) {
                            // This is the menu item to add to our DB

                            // Create new menu item
                            craft()->cpNav_nav->createNav(array(
                                'layoutId' => $layout->id,
                                'handle' => $key,
                                'label' => $value['label'],
                                'url' => array_key_exists('url', $value) ? $value['url'] : $key,
                                'order' => $i,
                            ));

                            if ($layoutId == $layout->id) {
                                $allNavs = craft()->cpNav_nav->getNavsByLayoutId($layoutId);
                            }
                        }

                        $i++;
                    }
                } else {
                    // Some menu items have been deleted, we need to as well

                    foreach ($manualNav as $nav) {
                        if (!array_key_exists($nav->handle, $navs)) {
                            // This is the menu item to delete from our DB
                            $navModel = craft()->cpNav_nav->getNavById($nav->id);

                            // Remove from DB
                            craft()->cpNav_nav->deleteNav($navModel);

                            if ($layoutId == $layout->id) {
                                $allNavs = craft()->cpNav_nav->getNavsByLayoutId($layoutId);
                            }
                        }
                    }
                }
            }
        }

        return $allNavs;
    }

    public function processUrl($newNav)
    {
        // Allow Enviroment Variables to be used in the URL
        $url = craft()->config->parseEnvironmentString(trim($newNav->url));

        // And a spcial case for global - always direct to first global set
        if ($newNav->handle == 'globals') {
            $globals = craft()->globals->getEditableSets();

            if ($globals) {
                $url = 'globals/' . $globals[0]->handle;
            }
        }

        return $url;
    }
    

}
