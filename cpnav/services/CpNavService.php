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
			$navRecord->prevUrl = $navRecord->url;
			$navRecord->manualNav = '0';

			$navRecord->save();
			$i++;
		}
	}

	public function reorderNav($navIds)
	{
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

		try {
			foreach ($navIds as $navOrder => $navId) {
				$navModel = $this->getNavById($navId);
				$navRecord = CpNavRecord::model()->findById($navModel->id);
				$navRecord->order = $navOrder+1;
				$navRecord->save();

				$navModel->order = $navRecord->order;
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

		return $this->getAllNavs();
	}

	public function toggleNav($navId, $toggle)
	{
		$navModel = $this->getNavById($navId);
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

		try {
			$navRecord = CpNavRecord::model()->findById($navModel->id);
			$navRecord->enabled = $toggle;
			$navRecord->save();

			$navModel->enabled = $navRecord->enabled;

			if ($transaction !== null) {
				$transaction->commit();
			}
		} catch (\Exception $e) {
			if ($transaction !== null) {
				$transaction->rollback();
			}

			throw $e;
		}

		return $this->getAllNavs();
	}

	public function saveNav(CpNavModel $nav)
	{
		$navRecord = CpNavRecord::model()->findById($nav->id);
		$navRecord->currLabel = $nav->currLabel;
		$navRecord->prevUrl = ($nav->prevUrl) ? $nav->prevUrl : $nav->url;
		$navRecord->url = $nav->url;
		$navRecord->save();

		$nav->currLabel = $navRecord->getAttribute('currLabel');

		return $nav;
	}

	// Clears out the DB - refreshed on next page load however. Used when restoring to defaults
	public function emptyTable()
	{
		$query = craft()->db->createCommand()->delete('cpnav');
	}

	public function createNav($value)
	{
		$navRecord = new CpNavRecord();

		$navRecord->handle = $value['handle'];
		$navRecord->currLabel = $value['label'];
		$navRecord->prevLabel = $value['label'];
		$navRecord->enabled = '1';
		$navRecord->url = $value['url'];
		$navRecord->prevUrl = $value['url'];
		$navRecord->order = '99';
		$navRecord->manualNav = '1';

		$navRecord->save();
	}

    public function deleteNav(CpNavModel $nav)
    {
		$navRecord = CpNavRecord::model()->findById($nav->id);

		$navRecord->delete();

		return $this->getAllNavs();
    }




}






