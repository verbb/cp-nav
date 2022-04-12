<?php
namespace verbb\cpnav\events;

use verbb\cpnav\models\Navigation;

use yii\base\Event;

class NavigationEvent extends Event
{
    // Properties
    // =========================================================================

    public ?Navigation $navigation = null;
    public bool $isNew = false;
}
