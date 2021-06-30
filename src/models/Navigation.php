<?php
namespace verbb\cpnav\models;

use verbb\cpnav\CpNav;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;

use yii\base\InvalidConfigException;

class Navigation extends Model
{
    // Constants
    // =========================================================================

    const TYPE_MANUAL = 'manual';
    const TYPE_DIVIDER = 'divider';


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
    public $type;
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
            ['newWindow', 'boolean'],

            // built-in "required" validator
            [['currLabel'], 'required'],
        ];
    }

    public function getLayout()
    {
        if ($this->layoutId === null) {
            throw new InvalidConfigException('Navigation is missing its layout ID');
        }

        if (($layout = CpNav::$plugin->getLayouts()->getLayoutById($this->layoutId)) === null) {
            throw new InvalidConfigException('Invalid layout ID: ' . $this->layoutId);
        }

        return $layout;
    }

    public function getFullUrl()
    {
        // An empty URL is okay
        if ($this->url === '') {
            return $this->url;
        }

        // Do some extra work on the url if needed
        $url = trim($this->url);

        // Support alias and env variables
        $url = Craft::parseEnv($url);

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

        return $url;
    }

    public function getIconPath()
    {
        try {
            if ($this->icon) {
                // If this is a path (plugin), set the correct key
                if (strpos($this->icon, DIRECTORY_SEPARATOR) !== false) {
                    // We've stored the full path to the icon-mask.svg file in our nav.
                    // But - this will change for each environment, so we need to fetch it properly!
                    $plugin = Craft::$app->getPlugins()->getPlugin($this->handle);

                    if ($plugin) {
                        $navItem = $plugin->getCpNavItem();

                        if (isset($navItem['icon'])) {
                            return $navItem['icon'];
                        }
                    }
                }

                return $this->icon;
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
                    // Check if this volume supports the path (ie, local volume)
                    $volumePath = $asset->getVolume()->path ?? null;

                    if ($volumePath) {
                        $path = FileHelper::normalizePath($volumePath . DIRECTORY_SEPARATOR . $asset->folderPath . DIRECTORY_SEPARATOR . $asset->filename);
                        $path = Craft::parseEnv($path);

                        if (@file_exists($path)) {
                            return $path;
                        }
                    }

                    return $asset->url;
                }
            }
        } catch (\Throwable $e) {
            CpNav::error(Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]));
        }

        return '';
    }

    public function generateNavItem()
    {
        // Despite having a custom, set menu for all users, we still need to check permissions
        // based on the current users' permision level. We wouldn't want to show a plugin nav item
        // if the user doesn't have access to it (even if defined in CP Nav).
        if (!$this->_checkPermission()) {
            return;
        }

        $item = [
            'id' => 'nav-' . $this->handle,
            'label' => Craft::t('app', $this->currLabel),
            'url' => $this->getFullUrl(),
        ];

        if ($icon = $this->getIconPath()) {
            if (strpos($icon, DIRECTORY_SEPARATOR) !== false) {
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

        if ($item['url'] === '') {
            $this->_insertJsForEmptyUrl();
        }

        if ($this->isDivider()) {
            // Ensure dividor items have unique IDs
            $id = $this->handle . '-' . uniqid();
            $item['id'] = 'nav-' . $id;

            $this->_insertJsForDivider($id);
        }

        return $item;
    }

    public function isManual()
    {
        return (bool)($this->type == self::TYPE_MANUAL);
    }

    public function isDivider()
    {
        return (bool)($this->type == self::TYPE_DIVIDER);
    }


    // Private Methods
    // =========================================================================

    private function _insertJsForNewWindow()
    {
        // Prevent this from loading when opening a modal window
        if (Craft::$app->getRequest()->isAjax) {
            return;
        }

        $js = 'Craft.CpNav.NewWindows.push("' . $this->handle . '");';
        Craft::$app->view->registerJs($js);
    }

    private function _insertJsForEmptyUrl()
    {
        // Prevent this from loading when opening a modal window
        if (Craft::$app->getRequest()->isAjax) {
            return;
        }
        
        $js = 'Craft.CpNav.EmptyUrls.push("' . $this->handle . '");';
        Craft::$app->view->registerJs($js);
    }

    private function _insertJsForDivider($id)
    {
        // Prevent this from loading when opening a modal window
        if (Craft::$app->getRequest()->isAjax) {
            return;
        }
        
        $js = 'Craft.CpNav.Dividers.push("' . $id . '");';
        Craft::$app->view->registerJs($js);

        // Add some CSS to hide it initially
        $css = '#global-sidebar #nav li#nav-' . $id . ' { opacity: 0; }';
        Craft::$app->view->registerCss($css);
    }

    private function _checkPermission()
    {
        $craftPro = Craft::$app->getEdition() === Craft::Pro;
        $isAdmin = Craft::$app->getUser()->getIsAdmin();
        $generalConfig = Craft::$app->getConfig()->getGeneral();

        // Prepare a key-may of permission-handling
        $permissionMap = [
            'entries' => Craft::$app->getSections()->getTotalEditableSections(),
            'globals' => Craft::$app->getGlobals()->getEditableSets(),
            'categories' => Craft::$app->getCategories()->getEditableGroupIds(),
            'assets' => Craft::$app->getVolumes()->getTotalViewableVolumes(),
            'users' => $craftPro && Craft::$app->getUser()->checkPermission('editUsers'),

            'utilities' => Craft::$app->getUtilities()->getAuthorizedUtilityTypes(),

            'graphql' => $isAdmin && $craftPro && $generalConfig->enableGql,
            'settings' => $isAdmin && $generalConfig->allowAdminChanges,
            'plugin-store' => $isAdmin,
        ];

        // Add each plugin
        foreach (Craft::$app->getPlugins()->getAllPlugins() as $plugin) {
            if ($pluginNavItem = $plugin->getCpNavItem()) {
                $permissionMap[$pluginNavItem['url']] = Craft::$app->getUser()->checkPermission('accessPlugin-' . $plugin->id);
            }
        }

        // Check if explicitly false
        if (isset($permissionMap[$this->handle])) {
            if ((bool)$permissionMap[$this->handle] === false) {
                return false;
            }
        }

        return true;
    }

}
