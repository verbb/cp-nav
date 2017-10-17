<?php
/**
 * CP Nav plugin for Craft CMS 3.x
 *
 * Control Panel Nav is a Craft CMS plugin to help manage your Control Panel navigation.
 *
 * @link      http://verbb.io
 * @copyright Copyright (c) 2017 Verbb
 */

namespace verbb\cpnav\controllers;

use verbb\cpnav\CpNav;

use Craft;
use craft\web\Controller;

/**
 * @author    Verbb
 * @package   CpNav
 * @since     2
 */
class NavigationController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
//    protected $allowAnonymous = ['index', 'do-something'];


    // Public Methods
    // =========================================================================

    /**
     * @return void
     */
    public function actionIndex()
    {
        $layoutId = $this->_getCurrentLayoutId();

//        $navs = craft()->cpNav_nav->getByLayoutId($layoutId);
//        $layouts = craft()->cpNav_layout->getAll();

        $layouts = CpNav::$plugin->layoutService->getAll();
        $navItems = CpNav::$plugin->navigation->getByLayoutId($layoutId);

        $this->renderTemplate('cp-nav/index', [
            'layouts'  => $layouts,
            'navItems' => $navItems,
        ]);
    }

//    /**
//     * @return mixed
//     */
//    public function actionDoSomething()
//    {
//        $result = 'Welcome to the NavigationController actionDoSomething() method';
//
//        return $result;
//    }


    // Private Methods
    // =========================================================================

    private function _getCurrentLayoutId()
    {
        if (Craft::$app->request->getParam('layoutId')) {
            return Craft::$app->request->getParam('layoutId');
//        } elseif (Craft::$app->request->getPost('layoutId')) {
//            return Craft::$app->request->getPost('layoutId');
        }

        return 1;
    }
}
