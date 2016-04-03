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
			'name'          => array(AttributeType::String),
			'isDefault'     => array(AttributeType::Bool),
			'permissions'    => array(AttributeType::Mixed),
		);
	}

	public function defineRelations()
	{
		return array(
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
