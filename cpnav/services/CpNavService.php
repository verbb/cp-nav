<?php
namespace Craft;

class CpNavService extends BaseApplicationComponent
{
	public function getAllNavs()
	{
		$navRecords = CpNavRecord::model()->ordered()->findAll();
		return CpNavModel::populateModels($navRecords);
	}

	public function getNavById($navId)
	{
		$navRecord = CpNavRecord::model()->findById($navId);

		if ($navRecord) {
			return CpNavModel::populateModel($navRecord);
		}
	}

	// This only happens once - pretty much as soon as the plugin is installed.
	// Populates the DB table with the original, untouched nav items
	public function populateInitially($nav)
	{
		$i = 0;
		foreach ($nav as $key => $value) {
			$navRecord = new CpNavRecord();

			$navRecord->handle = $key;
			$navRecord->currLabel = $value['label'];
			$navRecord->prevLabel = $value['label'];
			$navRecord->enabled = '1';
			$navRecord->order = $i;
			$navRecord->url = (array_key_exists('url', $value)) ? $value['url'] : $key;

			$navRecord->save();
			$i++;
		}
	}

	public function reorderNav($navIds)
	{
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

		try {
			foreach ($navIds as $navOrder => $navId) {
				$navRecord = CpNavRecord::model()->findById($navId);
				$navRecord->order = $navOrder+1;
				$navRecord->save();
			}

			if ($transaction !== null) {
				$transaction->commit();
			}
		} catch (\Exception $e) {
			if ($transaction !== null) {
				$transaction->rollback();
			}

			throw $e;
		}

		return true;
	}

	public function toggleNav($navId, $toggle)
	{
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

		try {
			$navRecord = CpNavRecord::model()->findById($navId);
			$navRecord->enabled = $toggle;
			$navRecord->save();

			if ($transaction !== null) {
				$transaction->commit();
			}
		} catch (\Exception $e) {
			if ($transaction !== null) {
				$transaction->rollback();
			}

			throw $e;
		}

		return true;
	}

	public function saveNav(CpNavModel $nav)
	{
		$navRecord = CpNavRecord::model()->findById($nav->id);
		$navRecord->currLabel = $nav->currLabel;
		$navRecord->save();

		$nav->currLabel = $navRecord->getAttribute('currLabel');

		return $nav;
	}







}






