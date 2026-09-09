<?php
namespace verbb\cpnav\models;

use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\CustomIcon;

use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

use yii\base\InvalidConfigException;

use DateTime;
use Throwable;

class LayoutNavItem extends Model
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
    public bool $isOrphan = false;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;
    public ?string $nodeKey = null;

    private ?Layout $_layout = null;
    private ?LayoutNavItem $_parent = null;
    private ?LayoutNavItem $_prevParent = null;
    private ?array $_originalNavItem = [];
    private ?array $_prevChildren = [];
    private ?array $_children = [];


    // Public Methods
    // =========================================================================

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
        // if (!str_contains($this->icon, '/') && !str_contains($this->icon, '\\')) {
        if (str_contains($this->icon, 'fontIcon:')) {
            return str_replace('fontIcon:', '', $this->icon);
        }

        return null;
    }

    public function getIcon(): ?string
    {
        // If set to `title` we want to fallback on the default
        if ($this->icon === 'title') {
            return null;
        }

        // Get the original navs path, so we can handle multi-environment paths correctly. Path's will be stored
        // in one environment, so they'll be different on another. The original nav will already have the correct path,
        // so it's efficient to just swap that in. This will also handle things like Craft' GQL, being `@appicons/graphql.svg`.
        // Be sure to check for Windows-based paths too.
        if (str_contains($this->icon, '/') || str_contains($this->icon, '\\')) {
            return $this->_originalNavItem['icon'] ?? $this->icon;
        }

        return $this->icon;
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

    /**
     * Absolute filesystem path for Craft `iconSvg()`, or empty when unset / missing.
     */
    public function getCustomIconPath(): bool|string|null
    {
        try {
            return CustomIcon::resolveSvgSource($this->customIcon) ?: '';
        } catch (Throwable $e) {
            CpNav::error('{e} - {f}: {l}.', ['e' => $e->getMessage(), 'f' => $e->getFile(), 'l' => $e->getLine()]);
        }

        return '';
    }

    public function getLayout(): ?Layout
    {
        if ($this->_layout !== null) {
            return $this->_layout;
        }

        if ($this->layoutId === null) {
            throw new InvalidConfigException('LayoutNavItem is missing its layout ID');
        }

        if (($layout = CpNav::$plugin->getLayouts()->getLayoutById($this->layoutId)) === null) {
            throw new InvalidConfigException('Invalid layout ID: ' . $this->layoutId);
        }

        return $this->_layout = $layout;
    }

    public function assignParent(LayoutNavItem $parent): void
    {
        $this->_parent = $parent;
    }

    public function getPrevParent(): ?LayoutNavItem
    {
        return $this->_prevParent;
    }

    public function getParent(): ?LayoutNavItem
    {
        return $this->_parent;
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
        return array_values(array_filter(
            $this->getChildren(),
            fn(LayoutNavItem $child) => $child->enabled,
        ));
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


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        return [
            [['currLabel'], 'required', 'when' => function($model) {
                return !$model->isDivider();
            }],
        ];
    }
}
