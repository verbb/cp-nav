<?php
namespace Craft;

class CpNavVariable
{
	public function defaultNavItems()
	{
		$layout = craft()->cpNav_layout->getDefaultLayout();
		
		if ($layout) {
			return craft()->cpNav_nav->getNavsByLayoutId($layout->id);
		}
	}

	public function navLayouts()
	{
		return craft()->cpNav_layout->getAllLayouts();
	}
}