<?php
namespace Craft;

class CpNav_NavService extends BaseApplicationComponent
{
	public function getAllNavs($indexBy = null)
	{
		$navRecords = CpNav_NavRecord::model()->ordered()->findAll();
		return CpNav_NavModel::populateModels($navRecords, $indexBy);
	}
	
	public function getAllNavsByAttributes($attributes = array(), $indexBy = null)
	{
		$navRecords = CpNav_NavRecord::model()->ordered()->findAllByAttributes($attributes);
		return CpNav_NavModel::populateModels($navRecords, $indexBy);
	}
	
	public function getNavsByLayoutId($layoutId, $indexBy = null)
	{
    	$navRecords = CpNav_NavRecord::model()->ordered()->findAllByAttributes(array('layoutId' => $layoutId));

		return CpNav_NavModel::populateModels($navRecords, $indexBy);
	}

	public function getNavById($navId)
	{
		$navRecord = CpNav_NavRecord::model()->findById($navId);

		if ($navRecord) {
			return CpNav_NavModel::populateModel($navRecord);
		}
	}

	public function getDefaultOrUserNavs($forUser = null)
	{
		/*if ($forUser) {
			$currentUser = craft()->users->getUserById($forUser);
		} else {
	        $currentUser = craft()->userSession->getUser();
		}

		// Check if a CPNav field is attached to the users profile. This doesn't happen on Craft Personal
		if (isset($currentUser->controlPanelLayout)) {
	        $userNavs = $currentUser->controlPanelLayout;
		} else {
	        $userNavs = null;
		}

		if ($userNavs) {
            // There's a user-specific layout - that needs to be shown
            $allNavs = array();

            $globalNavs = craft()->cpNav_nav->getNavsByLayoutId('1');

            // Grab the global navs, so we ensure we get the most uptodate list of available navs
            // but also helps to protect managing fields that're disabled globally
            foreach ($globalNavs as $globalNav) {
            	if ($globalNav->enabled) {
            		if (array_key_exists($globalNav->handle, $userNavs)) {
	            		$userNav = $userNavs[$globalNav->handle];

			            // This allows us to preserve user-defined order
	            		$order = array_search($globalNav->handle, array_keys($userNavs));

	            		$globalNav->enabled = $userNav['enabled'];
            		}

            		$allNavs[$order] = $globalNav;
            	}
            }

            // finish up for sorting correctly by key - keeps user order
            ksort($allNavs);
        } else {
            // No user-specific layout set - return the default
            //$allNavs = craft()->cpNav_nav->getAllNavsByAttributes(array('layoutId' => '1'));
        }*/

        $allNavs = craft()->cpNav_nav->getNavsByLayoutId('1');

        return $allNavs;
	}

	public function reorderNav($navIds)
	{
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

		try {
			foreach ($navIds as $navOrder => $navId) {
				$navModel = $this->getNavById($navId);
				$navRecord = CpNav_NavRecord::model()->findById($navModel->id);
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

		$navModel = $this->getNavById($navIds[0]);
		return $this->getNavsByLayoutId($navModel->layoutId);
	}

	public function toggleNav($navId, $toggle)
	{
		$navModel = $this->getNavById($navId);
		$transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

		try {
			$navRecord = CpNav_NavRecord::model()->findById($navModel->id);
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

		return $this->getNavsByLayoutId($navModel->layoutId);
	}

	public function saveNav(CpNav_NavModel $nav)
	{
		if (!$nav->id) {
			$navRecord = new CpNav_NavRecord();
		} else {
			$navRecord = CpNav_NavRecord::model()->findById($nav->id);
		}

		$navRecord->currLabel = $nav->currLabel;
		$navRecord->prevUrl = ($nav->prevUrl) ? $nav->prevUrl : $nav->url;
		$navRecord->url = $nav->url;
		$navRecord->newWindow = $nav->newWindow;

		$navRecord->save();

		$nav->currLabel = $navRecord->getAttribute('currLabel');
        $nav->url = craft()->config->parseEnvironmentString(trim($nav->url));

		return $nav;
	}

	public function createNav($value)
	{
		$navRecord = new CpNav_NavRecord();

		$navRecord->layoutId = $value['layoutId'];
		$navRecord->handle = $value['handle'];
		$navRecord->currLabel = $value['label'];
		$navRecord->prevLabel = $value['label'];
		$navRecord->enabled = '1';
		$navRecord->url = $value['url'];
		$navRecord->prevUrl = $value['url'];
		$navRecord->order = array_key_exists('order', $value) ? $value['order'] : '99';
		$navRecord->manualNav = array_key_exists('manual', $value) ? true : false;
        $navRecord->newWindow = array_key_exists('newWindow', $value) ? $value['newWindow'] : false;

		if ($navRecord->save()) {
			$nav = CpNav_NavModel::populateModel($navRecord);
			return array('success' => true, 'nav' => $nav);
		} else {
			return array('success' => false, 'error' => $navRecord->getErrors());
		}
	}

    public function deleteNav(CpNav_NavModel $nav)
    {
		$navRecord = CpNav_NavRecord::model()->findById($nav->id);

		$navRecord->delete();

		return $this->getNavsByLayoutId($nav->layoutId);
    }

    // Clears out the DB - refreshed on next page load however. Used when restoring to defaults
	public function restoreDefaults($layoutId)
	{
    	$navRecords = CpNav_NavRecord::model()->deleteAll('layoutId = :layoutId', array('layoutId' => $layoutId));
	}

}
