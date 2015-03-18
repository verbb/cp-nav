<?php
namespace Craft;

class CpNav_LayoutService extends BaseApplicationComponent
{
	public function getAllLayouts($indexBy = null)
	{
		$layoutRecords = CpNav_LayoutRecord::model()->ordered()->findAll();
		return CpNav_LayoutModel::populateModels($layoutRecords, $indexBy);
	}

	public function getLayoutById($layoutId)
	{
		$layoutRecord = CpNav_LayoutRecord::model()->findById($layoutId);

		if ($layoutRecord) {
			return CpNav_LayoutModel::populateModel($layoutRecord);
		}
	}

	public function getDefaultLayout()
	{
		$layoutRecord = CpNav_LayoutRecord::model()->ordered()->findByAttributes(array('isDefault' => '1'));

		if ($layoutRecord) {
			return CpNav_LayoutModel::populateModel($layoutRecord);
		}
	}

	public function saveLayout(CpNav_LayoutModel $layout)
	{
		$layoutRecord = CpNav_LayoutRecord::model()->findById($layout->id);

		$layoutRecord->name = $layout->name;

		$layoutRecord->save();

		$layout->name = $layoutRecord->getAttribute('name');

		return $layout;
	}

	public function createLayout($value, $manual = false)
	{
		$layoutRecord = new CpNav_LayoutRecord();

		$layoutRecord->name = $value['name'];

		$layoutRecord->save();
	}

    public function deleteLayout(CpNav_LayoutModel $layout)
    {
		$layoutRecord = CpNav_LayoutRecord::model()->findById($layout->id);

		// Delete all fields for this layout
    	$navRecords = CpNav_NavRecord::model()->deleteAll('layoutId = :layoutId', array('layoutId' => $layout->id));

		$layoutRecord->delete();

		return true;
    }






}

