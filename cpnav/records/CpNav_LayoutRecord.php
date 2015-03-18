<?php
namespace Craft;

class CpNav_LayoutRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'cpnav_layouts';
	}

	protected function defineAttributes()
	{
		return array(
			'name'			=> array(AttributeType::String),
			'isDefault'		=> array(AttributeType::Bool),
		);
	}

	public function defineRelations()
	{
		return array(
			//'user' => array(static::HAS_MANY, 'CpNav_UserRecord', 'userId'),
			'nav' => array(static::HAS_MANY, 'CpNav_NavRecord', 'navId'),
		);
	}

	public function scopes()
	{
		return array(
			'ordered' => array('order' => 'name'),
		);
	}
}
