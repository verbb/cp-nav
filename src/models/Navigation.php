<?php
namespace verbb\cpnav\models;

use verbb\cpnav\CpNav;
use verbb\cpnav\models\Settings;
use verbb\cpnav\helpers\Permissions;

use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

use yii\base\InvalidConfigException;

use DateTime;
use Throwable;

class Navigation extends Model
{
    // Constants
    // =========================================================================

    public const TYPE_CRAFT = 'craft';
    public const TYPE_PLUGIN = 'plugin';
    public const TYPE_MANUAL = 'manual';
    public const TYPE_DIVIDER = 'divider';


    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?int $layoutId = null;
    public ?string $handle = null;
    public ?string $prevLabel = null;
    public ?string $currLabel = null;
    public ?bool $enabled = null;
    public ?int $sortOrder = null;
    public ?int $prevLevel = null;
    public ?int $level = null;
    public ?int $prevParentId = null;
    public ?int $parentId = null;
    public ?string $prevUrl = null;
    public ?string $url = null;
    public ?string $icon = null;
    public ?string $customIcon = null;
    public ?string $type = null;
    public bool $newWindow = false;
    public ?string $subnavBehaviour = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    private ?Layout $_layout = null;
    private ?Navigation $_parent = null;
    private ?Navigation $_prevParent = null;
    private ?array $_originalNavItem = [];
    private ?array $_prevChildren = [];
    private ?array $_children = [];


    // Public Methods
    // =========================================================================

    public function defineRules(): array
    {
        return [
            [['currLabel'], 'required', 'when' => function($model) {
                return !$model->isDivider();
            }],
        ];
    }

    public function getConfig(): array
    {
        return [
            'layout' => $this->getLayout()->uid,
            'handle' => $this->handle,
            'prevLabel' => $this->prevLabel,
            'currLabel' => $this->currLabel,
            'enabled' => $this->enabled,
            'sortOrder' => $this->sortOrder,
            'prevLevel' => $this->prevLevel,
            'level' => $this->level,
            'prevParent' => $this->getPrevParent()->uid ?? null,
            'parent' => $this->getParent()->uid ?? null,
            'prevUrl' => $this->prevUrl,
            'url' => $this->url,
            'icon' => $this->icon,
            'customIcon' => $this->customIcon,
            'type' => $this->type,
            'newWindow' => $this->newWindow,
            'subnavBehaviour' => $this->subnavBehaviour,
        ];
    }

    public function isCraft(): bool
    {
        return $this->type == self::TYPE_CRAFT;
    }

    public function isPlugin(): bool
    {
        return $this->type == self::TYPE_PLUGIN;
    }

    public function isManual(): bool
    {
        return $this->type == self::TYPE_MANUAL;
    }

    public function isDivider(): bool
    {
        return $this->type == self::TYPE_DIVIDER;
    }

    public function isSubnav(bool $usePrev = false): bool
    {
        return ($usePrev) ? ($this->prevLevel === 2) : ($this->level === 2);
    }

    public function isSelected(): bool
    {
        $path = Craft::$app->getRequest()->getPathInfo();

        if ($path === 'myaccount') {
            $path = 'users';
        }

        // Compare using relative URLs
        return $this->url == $path || str_starts_with($path, $this->url . '/');
    }

    public function getLabel(): string
    {
        return Craft::t('app', $this->currLabel) ?? '';
    }

    public function getId(): string
    {
        if ($this->isDivider()) {
            // Ensure divider items have unique IDs
            return 'nav-' . StringHelper::appendRandomString($this->handle . '-', 16);
        }

        return 'nav-' . $this->handle;
    }

    public function getBadgeCount(): int
    {
        return $this->_originalNavItem['badgeCount'] ?? 0;
    }

    public function getFontIcon(): ?string
    {
        // Ignore any icon with a directory separator - that's not an icon font
        // Be sure to check for Windows-based paths too.
        if (!str_contains($this->icon, '/') && !str_contains($this->icon, '\\')) {
            return $this->icon;
        }

        return null;
    }

    public function getIcon(): ?string
    {
        // Get custom icon content - takes precedence
        if ($customIcon = $this->getCustomIconPath()) {
            return $customIcon;
        }

        // Get the original navs path, so we can handle multi-environment paths correctly. Path's will be stored
        // in one environment, so they'll be different on another. The original nav will already have the correct path,
        // so it's efficient to just swap that in. This will also handle things like Craft' GQL, being `@appicons/graphql.svg`.
        // Be sure to check for Windows-based paths too.
        if (str_contains($this->icon, '/') || str_contains($this->icon, '\\')) {
            return $this->_originalNavItem['icon'] ?? $this->icon;
        }

        return null;
    }

    public function getUrl(): ?string
    {
        // Do some extra work on the url if needed
        $url = trim($this->url);

        // An empty URL is okay
        if ($url === '') {
            return null;
        }

        // Support alias and env variables
        $url = App::parseEnv($url);

        return UrlHelper::url($url);
    }

    public function getCustomIconPath(): bool|string|null
    {
        try {
            if ($this->customIcon) {
                $customIcon = Json::decode($this->customIcon)[0] ?? null;

                if ($asset = Craft::$app->assets->getAssetById($customIcon)) {
                    // Check if this volume supports the path (ie, local volume)
                    $volumePath = $asset->getVolume()->path ?? null;

                    if ($volumePath) {
                        $path = FileHelper::normalizePath($volumePath . '/' . $asset->folderPath . '/' . $asset->filename);
                        $path = App::parseEnv($path);

                        if (@file_exists($path)) {
                            return $path;
                        }
                    }

                    return $asset->url;
                }
            }
        } catch (Throwable $e) {
            CpNav::error(Craft::t('app', '{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]));
        }

        return '';
    }

    public function getSubnavBehaviour(): ?string
    {
        if ($this->getChildren()) {
            /* @var Settings $settings */
            $settings = CpNav::$plugin->getSettings();

            $behaviour = $settings->defaultSubnavBehaviour;

            if ($this->subnavBehaviour) {
                $behaviour = $this->subnavBehaviour;
            }

            return $behaviour;
        }

        return null;
    }

    public function getLayout(): ?Layout
    {
        if ($this->_layout !== null) {
            return $this->_layout;
        }

        if ($this->layoutId === null) {
            throw new InvalidConfigException('Navigation is missing its layout ID');
        }

        if (($layout = CpNav::$plugin->getLayouts()->getLayoutById($this->layoutId)) === null) {
            throw new InvalidConfigException('Invalid layout ID: ' . $this->layoutId);
        }

        return $this->_layout = $layout;
    }

    public function getPrevParent(): ?Navigation
    {
        if ($this->_prevParent !== null) {
            return $this->_prevParent;
        }

        if (!$this->prevParentId) {
            return null;
        }

        return $this->_prevParent = CpNav::$plugin->getNavigations()->getNavigationById($this->prevParentId);
    }

    public function getParent(): ?Navigation
    {
        if ($this->_parent !== null) {
            return $this->_parent;
        }

        if (!$this->parentId) {
            return null;
        }

        return $this->_parent = CpNav::$plugin->getNavigations()->getNavigationById($this->parentId);
    }

    public function getPrevChildren(): array
    {
        return $this->_prevChildren;
    }

    public function setPrevChildren($value): void
    {
        $this->_prevChildren = $value;
    }

    public function addPrevChild($value): void
    {
        $this->_prevChildren[] = $value;
    }

    public function getChildren(): array
    {
        return $this->_children;
    }

    public function setChildren($value): void
    {
        $this->_children = $value;
    }

    public function addChild($value): void
    {
        $this->_children[] = $value;
    }

    public function getChildrenForCurrentUser(): array
    {
        $children = [];

        foreach ($this->getChildren() as $child) {
            $permission = null;

            // For plugins, check if this subnav exists for this current user.
            // We'll assume that if not, it's a permissions thing, and hide it.
            if ($child->isPlugin()) {
                $permission = Permissions::checkPluginSubnavPermission($child);
            }

            if ($permission === false || !$child->enabled) {
                continue;
            }

            $children[] = $child;
        }

        // Return no children if the subnav is only set to show when active (and this isn't active)
        if ($this->getSubnavBehaviour() === Settings::SUBNAV_DEFAULT && !$this->isSelected()) {
            return [];
        }

        return $children;
    }

    public function setOriginalNavItem($navItem): void
    {
        $subnavs = ArrayHelper::remove($navItem, 'subnav');

        $this->_originalNavItem = $navItem;

        // Setup each child with their subnav original nav. Don't forget to look at the
        // old nav's children, because they might've been moved!
        if ($subnavs) {
            foreach ($this->getPrevChildren() as $child) {
                $originalSubNav = $subnavs[$child->handle] ?? [];

                if ($originalSubNav) {
                    $child->setOriginalNavItem($originalSubNav);
                }
            }
        }
    }
}
