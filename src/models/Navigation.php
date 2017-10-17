<?php
/**
 * CP Nav plugin for Craft CMS 3.x
 *
 * Control Panel Nav is a Craft CMS plugin to help manage your Control Panel navigation.
 *
 * @link      http://verbb.io
 * @copyright Copyright (c) 2017 Verbb
 */

namespace verbb\cpnav\models;

use verbb\cpnav\CpNav;

use Craft;
use craft\base\Model;

/**
 * @author    Verbb
 * @package   CpNav
 * @since     2
 *
 * @property int $id
 */
class Navigation extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $layoutId;

    /**
     * @var string
     */
    public $handle;

    /**
     * @var string
     */
    public $prevLabel;

    /**
     * @var string
     */
    public $currLabel;

    /**
     * @var bool
     */
    public $enabled;

    /**
     * @var string
     */
    public $order;

    /**
     * @var string
     */
    public $prevUrl;

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $icon;

    /**
     * @var mixed
     */
    public $customIcon;

    /**
     * @var bool
     */
    public $manualNav;

    /**
     * @var bool
     */
    public $newWindow;

    // Model-only

    /**
     * @var string
     */
    public $craftIcon;

    /**
     * @var string
     */
    public $pluginIcon;


    // Public Methods
    // =========================================================================

    public function __construct($attributes = null)
    {
        parent::__construct($attributes);

        // Populate the Craft and Plugin icons as soon as we populate models - i.e - getById, getAll, etc
        if ($this->icon) {
            if (substr($this->icon, 0, 7) == 'iconSvg') {
                $this->pluginIcon = $this->_getPluginIcon($this->icon);
            } else {
                $this->craftIcon = $this->icon;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            ['id', 'int'],
            ['layoutId', 'int'],
            ['handle', 'string'],
            ['prevLabel', 'string'],
            ['currLabel', 'string'],
            ['enabled', 'bool'],
            ['order', 'string'],
            ['prevUrl', 'string'],
            ['url', 'string'],
            ['icon', 'string'],
            ['customIcon', 'mixed'],
            ['manualNav', 'bool'],
            ['newWindow', 'bool'],
            ['craftIcon', 'string'],
            ['pluginIcon', 'string'],
        ];
    }


    // Private Methods
    // =========================================================================

    private function _getPluginIcon($icon)
    {
        // Database stores plugin icons as "iconSvg-pluginHandle"
        $lcHandle = substr($icon, 8);
        $iconPath = Craft::$app->path->getPluginIconsPath() . $lcHandle . '/resources/icon-mask.svg';

//        if (IOHelper::fileExists($iconPath)) {
        if (@file_exists($iconPath)) {
//            $iconSvg = IOHelper::getFileContents($iconPath);
            $iconSvg = @file_get_contents($iconPath);
        } else {
            $iconSvg = false;
        }

        return $iconSvg;
    }
}
