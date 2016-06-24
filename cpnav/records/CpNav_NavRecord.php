<?php
namespace Craft;

class CpNav_NavRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'cpnav_navs';
    }

    protected function defineAttributes()
    {
        return array(
            'handle'        => array(AttributeType::Handle),
            'prevLabel'     => array(AttributeType::String),
            'currLabel'     => array(AttributeType::String),
            'enabled'       => array(AttributeType::Bool, 'default' => true),
            'order'         => array(AttributeType::Number, 'default' => 0),
            'prevUrl'       => array(AttributeType::String),
            'url'           => array(AttributeType::String),
            'icon'          => array(AttributeType::String),
            'customIcon'    => array(AttributeType::String),
            'manualNav'     => array(AttributeType::Bool),
            'newWindow'     => array(AttributeType::Bool),
        );
    }

    public function defineRelations()
    {
        return array(
            'layout'  => array(static::BELONGS_TO, 'CpNav_LayoutRecord'),
        );
    }

    public function scopes()
    {
        return array(
            'ordered' => array('order' => '`order`'),
        );
    }
}
