<?php
namespace verbb\cpnav\models;

use verbb\cpnav\CpNav;

use craft\base\Model;

use DateTime;

class Layout extends Model
{
    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?string $name = null;
    public bool $isDefault = false;
    public ?array $permissions = null;
    public ?int $sortOrder = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;


    // Public Methods
    // =========================================================================

    public function getNavigations(): array
    {
        return CpNav::$plugin->getNavigations()->getAllNavigationsByLayoutId($this->id);
    }

    public function getConfig(): array
    {
        return [
            'name' => $this->name,
            'isDefault' => $this->isDefault,
            'permissions' => $this->permissions,
            'sortOrder' => $this->sortOrder,
        ];
    }
}
