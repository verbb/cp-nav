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
     * Populate the Craft and Plugin icons as soon as we populate models - i.e - getById, getAll, etc
     *
     * @param array $attributes
     */
    public function __construct($attributes = null)
    {
        parent::__construct($attributes);

        if ($this->icon) {
            // If this is a plugin, we've stored the full path to the icon-mask.svg file. 
            // But - this will change for each environment, so we need to fetch it properly!
            if (strpos($this->icon, '/') !== false) {
                $plugin = Craft::$app->getPlugins()->getPlugin($this->handle);

                if ($plugin) {
                    $navItem = $plugin->getCpNavItem();

                    if (isset($navItem['icon'])) {
                        $this->pluginIcon = @file_get_contents($navItem['icon']);
                    }
                }
            } else {
                $this->craftIcon = $this->icon;
            }
        }

        // Get custom icon content
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
            if (\is_string($value)) {
                $url = str_replace('{' . $key . '}', $value, $url);
            }
        }

        // Support siteUrl
        $siteUrl = Craft::$app->getConfig()->getGeneral()->siteUrl;

        if (\is_string($siteUrl)) {
            $url = str_replace('{siteUrl}', $siteUrl, $url);
        }

        // And a special case for global - always direct to first global set
        if ($this->handle == 'globals') {
            $globals = Craft::$app->globals->getEditableSets();

            if ($globals) {
                $url = 'globals/' . $globals[0]->handle;
            }
        }

        $this->parsedUrl = $url;
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

            // built-in "required" validator
            [['currLabel', 'url'], 'required'],
        ];
    }
}
