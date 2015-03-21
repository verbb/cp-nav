<?php
namespace Craft;

class CpNav_NavModel extends BaseModel
{
	protected function defineAttributes()
	{
		return array(
			'id'			=> array(AttributeType::Number),
			'layoutId'		=> array(AttributeType::Number),
			'handle'		=> array(AttributeType::Handle),
			'prevLabel'		=> array(AttributeType::String),
			'currLabel'		=> array(AttributeType::String),
			'enabled'		=> array(AttributeType::Bool),
            'order'			=> array(AttributeType::Number),
            'prevUrl'		=> array(AttributeType::String),
            'url'			=> array(AttributeType::String),
            'manualNav'		=> array(AttributeType::Bool),
            'newWindow'		=> array(AttributeType::Bool),
		);
	}
}