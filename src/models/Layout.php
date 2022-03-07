<?php
namespace verbb\cpnav\models;

use verbb\cpnav\CpNav;

use craft\base\Model;
use craft\helpers\Json;

use DateTime;

class Layout extends Model
{
    // Properties
    // =========================================================================

    public ?int $id = null;
    public ?string $name = null;
    public bool $isDefault = false;
    public array $permissions = [];
    public ?int $sortOrder = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;


    // Public Methods
    // =========================================================================

    public function rules(): array
    {
        return [
            ['id', 'integer'],

            // built-in "string" validator
            ['name', 'string', 'min' => 1],

            // built-in "required" validator
            [['name'], 'required'],
        ];
    }

    public function getNavigations(): array
    {
        return CpNav::$plugin->getNavigations()->getNavigationsByLayoutId($this->id, true) ?? [];
    }
}
