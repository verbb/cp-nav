<?php

namespace verbb\cpnav\models;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\helpers\UrlHelper;

class Navigation extends Model
{

    // Public Properties
    // =========================================================================

    /**
     * @var integer
     */
    public $id;

    /**
     * @var integer
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
     * @var boolean
     */
    public $enabled;

    /**
     * @var integer
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
     * @var string
     */
    public $customIcon;

    /**
     * @var boolean
     */
    public $manualNav;

    /**
     * @var boolean
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

    /**
     * @var string
     */
    public $parsedUrl;

    /**
     * @var \DateTime
     */
    public $dateCreated;

    /**
     * @var \DateTime
     */
    public $dateUpdated;

    /**
     * @var string
     */
    public $uid;


    // Public Methods
    // =========================================================================

    /**
     * Navigation constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = null)
    {
        parent::__construct($attributes);

        // Populate the Craft and Plugin icons as soon as we populate models - i.e - getById, getAll, etc
        if ($this->icon) {
            if (substr($this->icon, 0, 7) == 'iconSvg') {
                $this->pluginIcon = $this->_getPluginIcon($this->handle);
            } else {
                $this->craftIcon = $this->icon;
            }
        }

        // Set custom icon path if set
        if ($this->customIcon) {

            // json decode custom icon id
            $customIcon = json_decode($this->customIcon)[0];
            $asset = Craft::$app->assets->getAssetById($customIcon);

            if ($asset) {
                $path = $asset->getVolume()->path . '/' . $asset->folderPath . $asset->filename;

                if (@file_exists($path)) {
                    $this->pluginIcon = @file_get_contents($path);
                }
            }
        }

        // Do some extra work on the url if needed
        $url = trim($this->url);

        // Allow Environment Variables to be used in the URL
        foreach (Craft::$app->getConfig()->getConfigFromFile('general') as $key => $value) {
            $url = str_replace('{' . $key . '}', $value, $url);
        }

        // Support siteUrl
        $url = str_replace('{siteUrl}', Craft::$app->getConfig()->getGeneral()->siteUrl, $url);

        // And a spcial case for global - always direct to first global set
        if ($this->handle == 'globals') {
            $globals = Craft::$app->globals->getEditableSets();

            if ($globals) {
                $url = 'globals/' . $globals[0]->handle;
            }
        }

        $this->parsedUrl = UrlHelper::url($url);
    }

    /**
     * Returns the attribute labels.
     *
     * @return array
     */
    public function attributeLabels(): array
    {
        return [
            'currLabel' => Craft::t('cp-nav', 'Label'),
            'url'       => Craft::t('cp-nav', 'URL'),
        ];
    }

    /**
     * Returns the validation rules for attributes.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            ['id', 'integer'],
            ['layoutId', 'integer'],
            ['handle', 'string'],
            ['prevLabel', 'string'],

            // built-in "string" validator
            ['currLabel', 'string', 'min' => 1],

            ['enabled', 'boolean'],
            ['order', 'integer'],
            ['prevUrl', 'string'],

            // built-in "string" validator
            ['url', 'string', 'min' => 1],

            ['icon', 'string'],
            ['customIcon', 'string'],
            ['manualNav', 'boolean'],
            ['newWindow', 'boolean'],
            ['craftIcon', 'string'],
            ['pluginIcon', 'string'],

            // built-in "required" validator
            [['currLabel', 'url'], 'required'],
        ];
    }


    // Private Methods
    // =========================================================================

    private function _getPluginIcon($handle)
    {
        $iconSvg = false;

        if (($plugin = Craft::$app->getPlugins()->getPlugin($handle)) !== null) {
            /** @var Plugin $plugin */
            $getCpNavItem = $plugin->getCpNavItem();
            $iconSvg = $getCpNavItem['iconSvg'];
        }

        return $iconSvg;
    }
}
