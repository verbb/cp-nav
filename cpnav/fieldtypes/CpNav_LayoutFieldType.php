<?php
namespace Craft;

class CpNav_LayoutFieldType extends BaseFieldType
{
    public function getName()
    {
        return Craft::t('Control Panel Layout');
    }

    public function isSelectable()
    {
        return false;
    }

    public function getInputHtml($name, $value)
    {
        $layoutId = $this->getSettings()->layoutId;

        $userId = $this->element->id;

        $allNavs = craft()->cpNav_nav->getDefaultOrUserNavs($userId);

        return craft()->templates->render('cpNav/fields/input', array(
            'name'     => $name,
            'value'    => $value,
            'settings' => $this->getSettings(),
            'navItems' => $allNavs,
        ));
    }

    public function getSettingsHtml()
    {
        return craft()->templates->render('cpNav/fields/settings', array(
            'settings' => $this->getSettings()
        ));
    }

    public function prepValue($value)
    {
        //var_dump($value);
        return $value;
    }

    public function prepValueFromPost($value)
    {
        //var_dump($value);
        return $value;
    }

    public function prepSettings($settings)
    {
        //var_dump($settings);
        return $settings;
    }

    public function defineContentAttribute()
    {
        return AttributeType::Mixed;
    }

    protected function defineSettings()
    {
        return array(
            'layoutId'   => AttributeType::Number,
        );
    }

}