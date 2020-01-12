<?php
namespace verbb\cpnav\models;

use Craft;
use craft\base\Model;

class Layout extends Model
{
    // Public Properties
    // =========================================================================

    public $id;
    public $name;
    public $isDefault;
    public $permissions;
    public $dateCreated;
    public $dateUpdated;
    public $uid;


    // Public Methods
    // =========================================================================

    public function attributeLabels(): array
    {
        return [
            'name'        => Craft::t('cp-nav', 'Name'),
            'isDefault'   => Craft::t('cp-nav', 'Is Default'),
            'permissions' => Craft::t('cp-nav', 'Permissions'),
        ];
    }

    public function rules(): array
    {
        return [
            ['id', 'integer'],

            // built-in "string" validator
            ['name', 'string', 'min' => 1],

            ['isDefault', 'boolean'],
            ['permissions', 'string'],

            // built-in "required" validator
            [['name'], 'required'],
        ];
    }
}
