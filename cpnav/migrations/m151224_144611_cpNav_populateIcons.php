<?php
namespace Craft;

class m151224_144611_cpNav_populateIcons extends BaseMigration
{
    public function safeUp()
    {
        // Populate icons
        $CpVariable = new CpVariable();
        $defaultNavs = $CpVariable->nav();

        $navs = craft()->cpNav_nav->getByLayoutId(1);

        if ($navs) {
            foreach ($navs as $nav) {
                if (isset($defaultNavs[$nav->handle])) {
                    $stockNav = $defaultNavs[$nav->handle];

                    if (isset($stockNav['icon'])) {
                        $icon = $stockNav['icon'];
                    } else if (isset($stockNav['iconSvg'])) {
                        $icon = 'iconSvg-' . $nav->handle;
                    } else {
                        $icon = '';
                    }

                    $nav->icon = $icon;

                    craft()->cpNav_nav->save($nav);            
                }
            }
        }

        return true;
    }
}
