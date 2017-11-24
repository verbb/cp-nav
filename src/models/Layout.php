<?php

namespace verbb\cpnav\models;

use Craft;
use craft\base\Model;

class Layout extends Model
{

    // Public Properties
    // =========================================================================

    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var boolean
     */
    public $isDefault;

    /**
     * @var string
     */
    public $permissions;

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
     * Returns the attribute labels.
     *
     * @return array
     */
    public function attributeLabels(): array
    {
        return [
            'name'        => Craft::t('cp-nav', 'Name'),
            'isDefault'   => Craft::t('cp-nav', 'Is Default'),
            'permissions' => Craft::t('cp-nav', 'Permissions'),
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

            // built-in "string" validator
            ['name', 'string', 'min' => 1],

            ['isDefault', 'boolean'],
            ['permissions', 'string'],

            // built-in "required" validator
            [['name'], 'required'],
        ];
    }
}
