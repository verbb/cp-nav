<?php
namespace Craft;

class CpNavRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'cpnav';
	}

	protected function defineAttributes()
	{
		return array(
			'handle'		=> array(AttributeType::Handle),
			'prevLabel'		=> array(AttributeType::String),
			'currLabel'		=> array(AttributeType::String),
			'enabled'		=> array(AttributeType::Bool, 'default' => true),
            'order'			=> array(AttributeType::Number, 'default' => 0),
		);
	}

	public function defineRelations()
	{
		return array(
			//'userGroup' => array(static::BELONGS_TO, 'UserGroupRecord', 'onDelete' => static::CASCADE),
		);
	}

	public function scopes()
	{
		return array(
			'ordered' => array('order' => '`order`'),
		);
	}
}
