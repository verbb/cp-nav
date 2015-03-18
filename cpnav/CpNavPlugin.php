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
        return '1.5';
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
        return craft()->templates->render('cpnav/settings', array(
            'settings' => $this->getSettings(),
        ));
    }

    public function onAfterInstall()
    {   
        // Only the 2640 build of Craft supports 'modifyCpNav()'
        $minBuild = '2640';

        if (craft()->getBuild() < $minBuild) {
            craft()->plugins->disablePlugin($this->getClassHandle());

            craft()->plugins->uninstallPlugin($this->getClassHandle());

            craft()->userSession->setError(Craft::t('{plugin} only works on Craft build {build} or higher', array(
                'plugin' => $this->getName(),
                'build' => $minBuild,
            )));
        }

/*


$thirdPartyField = new FieldModel();
        $thirdPartyField->groupId      = 1;
        $thirdPartyField->name         = Craft::t('Third Party');
        $thirdPartyField->handle       = 'thirdParty';
        $thirdPartyField->translatable = false;
        $thirdPartyField->type         = 'PlainText';

        craft()->fields->saveField($thirdPartyField);

        // Create the new user field layout

        $fieldLayout = craft()->fields->getLayoutByType(ElementType::User);
        $fieldsIds = $fieldLayout->getFieldIds();
        $fieldsIds[] = $thirdPartyField->id;

        craft()->fields->deleteLayoutsByType(ElementType::User);

        $fieldLayout = craft()->fields->assembleLayout(
            array(
                Craft::t('Profile') => $fieldsIds,
            ),
            array(),
            false
        );
        $fieldLayout->type = ElementType::User;

        craft()->fields->saveLayout($fieldLayout, false);

        */












    }



    /* --------------------------------------------------------------
    * HOOKS
    * ------------------------------------------------------------ */
    
    public function modifyCpNav(&$nav)
    {
        $allNavs = craft()->cpNav_nav->getDefaultOrUserNavs();

        $nav = array();

        foreach ($allNavs as $newNav) {
            if ($newNav->enabled) {
                $nav[$newNav->handle] = array(
                    'label' => $newNav->currLabel,
                    'url'   => $newNav->url,
                );
            }
        }










        /*
        // First, are there any layouts for the specific user? Will return the default if none set for user.
        $userLayout = craft()->cpNav_layout->getLayoutForUser();

        if (!$userLayout) {
            // This means there are no user-defined layouts OR default ones. Time to create them.
            craft()->cpNav->setupDefaults($nav);
        } else {
            // We have a layout - now grab all the nav items to print out.
            $allNavs = craft()->cpNav_nav->getNavsByLayoutId($userLayout->id);

            // Important to compare the current Nav to the one stored. What if a new menu has been added by a plugin?
            $allNavs = craft()->cpNav->checkIfUpdateNeeded($userLayout->id, $allNavs, $nav);

            // Overriding this allows us to reorder items, otherwise they're stuck in instantiated order - Scary...
            $nav = array();

            foreach ($allNavs as $newNav) {
                if ($newNav->enabled) {
                    $nav[$newNav->handle] = array(
                        'label' => $newNav->currLabel,
                        'url'   => $newNav->url,
                    );
                }
            }
        }

        // Are there any Layouts in our DB? If not, we're starting from scratch
        if (count($userLayout) === 0) {
            //craft()->cpNav->setupDefaults($nav);
        } else {*/
            // If we have records, print them out instead of the regular CP Nav
            /*
            // Important to compare the current Nav to the one stored. What if a new menu has been added by a plugin?
            $allNavs = craft()->cpNav->checkIfUpdateNeeded($allNavs, $nav);

            // Overriding this allows us to reorder items, otherwise they're stuck in instantiated order - Scary...
            $nav = array();

            foreach ($allNavs as $newNav) {
                if ($newNav->enabled) {
                    $nav[$newNav->handle] = array(
                        'label' => $newNav->currLabel,
                        'url'   => $newNav->url,
                    );
                }
            }*/
        //}
        
    }
}

