<?php
namespace Craft;

class CpNav_NavModel extends BaseModel
{
    // Public Methods
    // =========================================================================

    public function __construct($attributes = null)
    {
        parent::__construct($attributes);

        // Populate the Craft and Plugin icons as soon as we populate models - i.e - getById, getAll, etc
        if ($this->icon) {
            if (substr($this->icon, 0, 7) == 'iconSvg') {
                $this->pluginIcon = $this->_getPluginIcon($this->icon);
            } else {
                $this->craftIcon = $this->icon;
            }
        }
    }



    // Private Methods
    // =========================================================================

    private function _getPluginIcon($icon)
    {
        // Database stores plugin icons as "iconSvg-pluginHandle"
        $lcHandle = substr($icon, 8);
        $iconPath = craft()->path->getPluginsPath() . $lcHandle . '/resources/icon-mask.svg';

        if (IOHelper::fileExists($iconPath)) {
            $iconSvg = IOHelper::getFileContents($iconPath);
        } else {
            $iconSvg = false;
        }

        return $iconSvg;
    }



    // Protected Methods
    // =========================================================================

    protected function defineAttributes()
    {
        return array(
            'id'            => array(AttributeType::Number),
            'layoutId'      => array(AttributeType::Number),
            'handle'        => array(AttributeType::String),
            'prevLabel'     => array(AttributeType::String),
            'currLabel'     => array(AttributeType::String),
            'enabled'       => array(AttributeType::Bool),
            'order'         => array(AttributeType::Number),
            'prevUrl'       => array(AttributeType::String),
            'url'           => array(AttributeType::String),
            'icon'          => array(AttributeType::String),
            'customIcon'    => array(AttributeType::Mixed),
            'manualNav'     => array(AttributeType::Bool),
            'newWindow'     => array(AttributeType::Bool),

            // Model-only
            'craftIcon'     => array(AttributeType::String),
            'pluginIcon'    => array(AttributeType::String),
        );
    }

}