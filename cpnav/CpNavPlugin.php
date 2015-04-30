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
        return '1.6.2';
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

    protected function defineSettings()
    {
        return array(
            'showQuickAddMenu'  => array( AttributeType::Bool, 'default' => false ),
        );
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

        //$this->addFieldToUserProfile();

    }

    public function onBeforeUninstall()
    {
        //$this->removeFieldToUserProfile();
    }

    public function init()
    {
        parent::init();

        $user = craft()->userSession->getUser();

        if (craft()->request->isCpRequest()) {
            $allNavs = craft()->cpNav_nav->getDefaultOrUserNavs();

            if ($user) {
                if ($this->getSettings()->showQuickAddMenu && $user->can('quickAddMenu')) {
                    $this->insertJsForQuickMenuAdd($allNavs);
                }
            }

            if ($allNavs) {
                foreach ($allNavs as $nav) {

                    // Allow links to be opened in new window - insert some small JS
                    if ($nav->newWindow) {
                        $this->insertJsForNewWindow($nav);
                    }

                    // Check to ensure this page is enabled - otherwise simply redirect to first available menu item
                    if (craft()->request->path == $nav->url) {
                        if (!$nav->enabled) {
                            $enabledNavs = craft()->cpNav_nav->getAllNavsByAttributes(array('enabled' => true));

                            // We're on a page that's disabled - redirect to the first enabled one!
                            craft()->request->redirect(UrlHelper::getUrl($enabledNavs[0]->url));
                        }
                    } else if (craft()->request->path == preg_replace(sprintf('/^(https?:\/\/)?(%s)?\/?%s\//', preg_quote(craft()->getSiteUrl(''), '/'), preg_quote(craft()->config->get('cpTrigger')), '/'), '', $nav->url) && $nav->enabled && $nav->manualNav) {

                        // Add some JavaScript to correct the selected nav item for manually added navigation items.
                        // Have to do this with JavaScript for now as the nav item selection is made after the modifyCpNav hook.
                        $this->insertJsForManualNavSelection($nav);
                    }
                }
            }
        }
    }


    /* --------------------------------------------------------------
    * JS FUNCTIONS
    * ------------------------------------------------------------ */

    public function insertJsForNewWindow($nav)
    {
        // Prevent this from loading when opening a modal window
        if (!craft()->request->isAjaxRequest()) {
            $navElement = '#header #nav li#nav-' . $nav->handle . ' a';
            $js = '$(function() { $("'.$navElement.'").attr("target", "_blank"); });';
            craft()->templates->includeJs($js);
        }
    }

    public function insertJsForQuickMenuAdd($navs)
    {
        // Prevent this from loading when opening a modal window
        if (!craft()->request->isAjaxRequest()) {
            $js = "$(function() {" .
                    "$('#header-actions').prepend('<li class=\"cpnav-quick-menu\"><a class=\"add-new-menu-item menubtn icon add\" href=\"#\" data-id=\"".$navs[0]->layoutId."\" title=\"Add Menu Item\"></a></li>');" . 
                "});";
            craft()->templates->includeJs($js);

            craft()->templates->includeCssResource('cpnav/css/cpnavMenu.css');
            craft()->templates->includeJsResource('cpnav/js/cpnavMenu.js');
        }
    }

    public function insertJsForManualNavSelection($nav)
    {
        // Prevent this from loading when opening a modal window
        if (!craft()->request->isAjaxRequest()) {
            $js = '$(function() { $("#nav a").removeClass("sel"); $("#nav li#nav-' . $nav->handle . ' a").addClass("sel"); });';
            craft()->templates->includeJs($js);
        }
    }



    /* --------------------------------------------------------------
    * FUNCTIONS
    * ------------------------------------------------------------ */
    
    /*public function addFieldToUserProfile()
    {
        $existingField = craft()->fields->getFieldbyHandle('controlPanelLayout');

        if ($existingField) {
            $thirdPartyField = $existingField;
        } else {
            $thirdPartyField = new FieldModel();
            $thirdPartyField->groupId      = 1;
            $thirdPartyField->name         = Craft::t('Control Panel Layout');
            $thirdPartyField->handle       = 'controlPanelLayout';
            $thirdPartyField->translatable = false;
            $thirdPartyField->type         = 'CpNav_Layout';

            craft()->fields->saveField($thirdPartyField);
        }

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
    }

    public function removeFieldToUserProfile()
    {
        // Get third party field
        $thirdPartyField = craft()->fields->getFieldByHandle('controlPanelLayout');

        // Remove field from layout
        $fieldLayout = craft()->fields->getLayoutByType(ElementType::User);
        $fieldsIds = $fieldLayout->getFieldIds();
        $fieldsIds = array_diff($fieldsIds, array($thirdPartyField->id));

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

        // Delete field
        craft()->fields->deleteField($thirdPartyField);
    }*/



    /* --------------------------------------------------------------
    * HOOKS
    * ------------------------------------------------------------ */
    
    public function modifyCpNav(&$nav)
    {
        if (craft()->request->isCpRequest()) {

            // Get either the default nav, or the user-defined nav
            $allNavs = craft()->cpNav_nav->getDefaultOrUserNavs();

            if (!$allNavs) {
                // This means there are no user-defined layouts OR default ones. Time to create them.
                craft()->cpNav->setupDefaults($nav);
            } else {

                // Important to compare the current Nav to the one stored. What if a new menu has been added by a plugin?
                $allNavs = craft()->cpNav->checkIfUpdateNeeded($allNavs, $nav);

                // Overriding this allows us to reorder items, otherwise they're stuck in instantiated order - Scary...
                $nav = array();

                foreach ($allNavs as $newNav) {

                    // Do some extra work on the url if needed
                    $url = craft()->cpNav->processUrl($newNav);

                    if ($newNav->enabled) {
                        $nav[$newNav->handle] = array(
                            'label' => $newNav->currLabel,
                            'url'   => $url,
                        );
                    }
                }
            }
        }
    }

    function registerUserPermissions()
    {
        return array(
            'quickAddMenu' => array('label' => Craft::t('Show Quick-Add Menu'))
        );
    }
}

