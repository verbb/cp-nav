<?php
namespace Craft;

class CpNavPlugin extends BasePlugin
{
    // =========================================================================
    // PLUGIN INFO
    // =========================================================================

    public function getName()
    {
        return Craft::t('Control Panel Nav');
    }

    public function getVersion()
    {
        return '1.7.7';
    }

    public function getSchemaVersion()
    {
        return '1.1.0';
    }

    public function getDeveloper()
    {
        return 'S. Group';
    }

    public function getDeveloperUrl()
    {
        return 'http://sgroup.com.au';
    }

    public function getPluginUrl()
    {
        return 'https://github.com/engram-design/CPNav';
    }

    public function getDocumentationUrl()
    {
        return $this->getPluginUrl() . '/blob/master/README.md';
    }

    public function getReleaseFeedUrl()
    {
        return 'https://raw.githubusercontent.com/engram-design/CPNav/master/changelog.json';
    }

    public function getSettingsUrl()
    {
        return 'cpnav';
    }

    public function registerCpRoutes()
    {
        return array(
            'cpnav' => array('action' => 'cpNav/nav/index'),
            'cpnav/layouts' => array('action' => 'cpNav/layout/index'),
        );
    }

    public function onBeforeInstall()
    {
        $version = craft()->getVersion();

        // Craft 2.6.2951 deprecated `craft()->getBuild()`, so get the version number consistently
        if (version_compare(craft()->getVersion(), '2.6.2951', '<')) {
            $version = craft()->getVersion() . '.' . craft()->getBuild();
        }

        // While Craft 2.3.2640 added 'modifyCpNav()', the CP layout changed in Craft 2.5
        if (version_compare($version, '2.5', '<')) {
            throw new Exception($this->getName() . ' requires Craft CMS 2.5+ in order to run.');
        }
    }

    public function onAfterInstall()
    {
        // Setup default Layouts and Nav items
        craft()->cpNav->setupDefaults();
    }


    // =========================================================================
    // HOOKS
    // =========================================================================

    public function modifyCpNav(&$nav)
    {
        // Don't run the plugins custom menu during a migration
        if (craft()->request->path == 'actions/update/updateDatabase') {
            return true;
        }

        if (craft()->request->isCpRequest()) {
            craft()->cpNav->modifyCpNav($nav);
        }
    }
}
