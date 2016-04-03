<?php
namespace Craft;

class CpNav_LayoutModel extends BaseModel
{
    protected function defineAttributes()
    {
        return array(
            'id'            => array(AttributeType::Number),
            'name'          => array(AttributeType::String),
            'isDefault'     => array(AttributeType::Bool),
            'permissions'   => array(AttributeType::Mixed),
        );
    }
}