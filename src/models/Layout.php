<?php
/**
 * CP Nav plugin for Craft CMS 3.x
 *
 * Control Panel Nav is a Craft CMS plugin to help manage your Control Panel navigation.
 *
 * @link      http://verbb.io
 * @copyright Copyright (c) 2017 Verbb
 */

namespace verbb\cpnav\models;

use verbb\cpnav\CpNav;

use Craft;
use craft\base\Model;

/**
 * @author    Verbb
 * @package   CpNav
 * @since     2
 *
 * @property int $id
 */
class Layout extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var bool
     */
    public $isDefault;

    /**
     * @var mixed
     */
    public $permissions;


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'name' => \Craft::t('cp-nav', 'Name'),
            'isDefault' => \Craft::t('cp-nav', 'Is Default'),
            'permissions' => \Craft::t('cp-nav', 'Permissions'),
        ];
    }

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            ['id', 'int'],
            ['name', 'string'],
            ['isDefault', 'string'],
            ['permissions', 'mixed'],
        ];
    }
}
