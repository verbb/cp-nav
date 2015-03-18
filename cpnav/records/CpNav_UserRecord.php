<?php
namespace Craft;

class CpNav_UserRecord extends BaseRecord
{
	public function getTableName()
	{
		return 'cpnav_users';
	}

	protected function defineAttributes()
	{
		return array(

		);
	}

	public function defineRelations()
    {
        return array(
            'layout'  => array(static::BELONGS_TO, 'CpNav_LayoutRecord'),
            'user'  => array(static::BELONGS_TO, 'UserRecord'),
        );
    }
}
