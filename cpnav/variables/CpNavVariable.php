<?php
namespace Craft;

class CpNavVariable
{
	public function navItems()
	{
		return craft()->cpNav->getAllNavs();
	}
}