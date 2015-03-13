<?php
namespace Craft;

class CpNavPlugin extends BasePlugin
{
    /* --------------------------------------------------------------
    * PLUGIN INFO
    * ------------------------------------------------------------ */

    public function getName()
    {
        return Craft::t('Control Panel Nav');
    }

    public function getVersion()
    {
        return '1.0';
    }

    public function getDeveloper()
    {
        return 'S. Group';
    }

    public function getDeveloperUrl()
    {
        return 'http://sgroup.com.au';
    }

    public function hasCpSection()
    {
        return false;
    }

    public function getSettingsHtml()
    {
        return craft()->templates->render( 'cpnav/settings', array(
            'settings' => $this->getSettings(),
        ) );
    }

    protected function defineSettings()
    {
        return array(

        );
    }

    public function onAfterInstall()
    {   
        // Only the 2640 build of Craft supports 'modifyCpNav()'
        /*
        $minBuild = '2640';

        if (craft()->getBuild() < $minBuild) {
            craft()->plugins->disablePlugin($this->getClassHandle());

            craft()->plugins->uninstallPlugin($this->getClassHandle());

            craft()->userSession->setError(Craft::t('{plugin} only works on Craft build {build} or higher', array(
                'plugin' => $this->getName(),
                'build' => $minBuild,
            )));
        }*/
    }



    /* --------------------------------------------------------------
    * HOOKS
    * ------------------------------------------------------------ */
    
    public function modifyCpNav(&$nav)
    {
        $allNavs = craft()->cpNav->getAllNavs();

        // Are there any nav items in our DB? If not, populate it immediately
        if (count($allNavs) === 0) {
            craft()->cpNav->populateInitially($nav);
        } else {
            // If we have records, print them out instead of the regular CP Nav

            // Overriding this allows us to reorder items, otherwise they're stuck in instantiated order - Scary...
            $nav = array();

            foreach ($allNavs as $newNav) {
                if ($newNav->enabled) {
                    $nav[$newNav->handle] = $newNav->currLabel;
                }
            }
        }
    }
}

