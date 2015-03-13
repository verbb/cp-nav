<?php
namespace Craft;

class CpNavModel extends BaseModel
{
	protected function defineAttributes()
	{
		return array(
			'id'			=> array(AttributeType::Number),
			//'userGroupId'	=> array(AttributeType::Number),
			'handle'		=> array(AttributeType::Handle),
			'prevLabel'		=> array(AttributeType::String),
			'currLabel'		=> array(AttributeType::String),
			'enabled'		=> array(AttributeType::Bool),
            'order'			=> array(AttributeType::Number),
            'url'			=> array(AttributeType::String),
		);
	}
}