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
class LayoutController extends Controller
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

    public function actionIndex()
    {
        $layouts = CpNav::$plugin->layoutService->getAll();

        $this->renderTemplate('cp-nav/layouts', array(
            'layouts' => $layouts,
        ));
    }

//    /**
//     * @return mixed
//     */
//    public function actionDoSomething()
//    {
//        $result = 'Welcome to the LayoutController actionDoSomething() method';
//
//        return $result;
//    }
}
