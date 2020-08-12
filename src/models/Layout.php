<?php
namespace verbb\cpnav\models;

use verbb\cpnav\CpNav;

use Craft;
use craft\base\Model;
use craft\helpers\Json;

class Layout extends Model
{
    // Public Properties
    // =========================================================================

    public $id;
    public $name;
    public $isDefault;
    public $permissions;
    public $sortOrder = 1;
    public $dateCreated;
    public $dateUpdated;
    public $uid;


    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        $this->permissions = Json::decodeIfJson($this->permissions, true);
    }

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

    public function getNavigations()
    {
        return CpNav::$plugin->getNavigations()->getNavigationsByLayoutId($this->id, true) ?? [];
    }
}
