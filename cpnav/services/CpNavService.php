<?php
namespace Craft;

class CpNavService extends BaseApplicationComponent
{
	public function getAllNavs($indexBy = null)
	{
		$navRecords = CpNavRecord::model()->ordered()->findAll();
		return CpNavModel::populateModels($navRecords, $indexBy);
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
	public function populateInitially($nav, $order = null)
	{
		$i = (!$order) ? 0 : $order;
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


    // Determines if there are any new CP menu items (from a plugin install or Craft)
    // And likewise determines if a plugin has been removed - no need to keep menu item.
    public function checkForNewAndOld($allNavs, $navs) {

    	// firstly, a quick size check between the default nav and our copy (manual links not included)
    	// will tell us if we need to look at whats been added or missing

    	// Get all records that are not manually created by user
    	$manualNav = CpNavRecord::model()->findAll(array('condition' => 'manualNav IS NULL OR manualNav <> 1', 'index' => 'handle'));

    	// if not equal, looks like something has changed!
    	if (count($manualNav) != count($navs)) {
    		if (count($manualNav) < count($navs)) {
    			// There are new menu items that have been added

    			foreach ($navs as $key => $value) {
    				if (!array_key_exists($key, $manualNav)) {
    					// This is the menu item to add to our DB

    					$this->populateInitially(array($key => $value), '99');

    					$allNavs = craft()->cpNav->getAllNavs();
    				}
    			}
    		} else {
    			// Some menu items have been deleted, we need to as well

    			foreach ($manualNav as $nav) {
    				if (!array_key_exists($nav->handle, $navs)) {
    					// This is the menu item to delete from our DB
    					$navModel = $this->getNavById($nav->id);

    					// remove from DB
    					$this->deleteNav($navModel);

    					$allNavs = craft()->cpNav->getAllNavs();
    				}
    			}
    		}
    	}

    	return $allNavs;
    }




}






