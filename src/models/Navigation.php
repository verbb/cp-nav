<?php
namespace verbb\cpnav\models;

use verbb\cpnav\CpNav;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;

class Navigation extends Model
{
    // Public Properties
    // =========================================================================

    public $id;
    public $layoutId;
    public $handle;
    public $prevLabel;
    public $currLabel;
    public $enabled;
    public $order;
    public $prevUrl;
    public $url;
    public $icon;
    public $customIcon;
    public $manualNav;
    public $newWindow;
    public $dateCreated;
    public $dateUpdated;
    public $uid;


    // Public Methods
    // =========================================================================

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

    public function getFullUrl()
    {
        // Do some extra work on the url if needed
        $url = trim($this->url);

        // Support alias
        $url = Craft::getAlias($url);

        // Allow Environment Variables to be used in the URL
        foreach (Craft::$app->getConfig()->getConfigFromFile('general') as $key => $value) {
            if (is_string($value)) {
                $url = str_replace('{' . $key . '}', $value, $url);
            }
        }

        // Support siteUrl
        $siteUrl = Craft::$app->getConfig()->getGeneral()->siteUrl;

        if (is_string($siteUrl)) {
            $url = str_replace('{siteUrl}', $siteUrl, $url);
        }

        // And a special case for global - always direct to first global set
        if ($this->handle == 'globals') {
            $globals = Craft::$app->globals->getEditableSets();

            if ($globals) {
                $url = 'globals/' . $globals[0]->handle;
            }
        }

        return $url;
    }

    public function getIconPath()
    {
        try {
            if ($this->icon) {
                // If this is a path (plugin), set the correct key
                if (strpos($this->icon, '/') !== false) {
                    // We've stored the full path to the icon-mask.svg file in our nav.
                    // But - this will change for each environment, so we need to fetch it properly!
                    $plugin = Craft::$app->getPlugins()->getPlugin($this->handle);

                    if ($plugin) {
                        $navItem = $plugin->getCpNavItem();

                        if (isset($navItem['icon'])) {
                            return $navItem['icon'];
                        }
                    }
                } else {
                    return $this->icon;
                }
            }
        } catch (\Throwable $e) {
            CpNav::error(Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]));
        }

        return '';
    }

    public function getCustomIconPath()
    {
        try {
            if ($this->customIcon) {
                $customIcon = json_decode($this->customIcon)[0];
                $asset = Craft::$app->assets->getAssetById($customIcon);

                if ($asset) {
                    $path = FileHelper::normalizePath($asset->getVolume()->path . DIRECTORY_SEPARATOR . $asset->folderPath . DIRECTORY_SEPARATOR . $asset->filename);
                    $path = Craft::getAlias($path);

                    if (@file_exists($path)) {
                        return $path;
                    }
                }
            }
        } catch (\Throwable $e) {
            CpNav::error(Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]));
        }

        return '';
    }

    public function generateNavItem()
    {
        $item = [
            'id' => 'nav-' . $this->handle,
            'label' => Craft::t('app', $this->currLabel),
            'url' => $this->getFullUrl(),
        ];

        if ($icon = $this->getIconPath()) {
            if (strpos($icon, '/') !== false) {
                $item['icon'] = $icon;
            } else {
                $item['fontIcon'] = $icon;
            }
        }

        // Get custom icon content
        if ($customIcon = $this->getCustomIconPath()) {
            $item['icon'] = $customIcon;
        }

        // Allow links to be opened in new window - insert some small JS
        if ($this->newWindow) {
            $this->_insertJsForNewWindow();
        }

        return $item;
    }


    // Private Methods
    // =========================================================================

    private function _insertJsForNewWindow()
    {
        // Prevent this from loading when opening a modal window
        if (!Craft::$app->getRequest()->isAjax) {
            $navElement = '#global-sidebar #nav li#nav-' . $this->handle . ' a';
            $js = '$(function() { $("' . $navElement . '").attr("target", "_blank"); });';
            
            Craft::$app->view->registerJs($js);
        }
    }
}
