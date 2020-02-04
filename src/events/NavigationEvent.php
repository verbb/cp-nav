<?php
namespace verbb\cpnav\events;

use yii\base\Event;

class NavigationEvent extends Event
{
    // Properties
    // =========================================================================

    public $navigation;

    public $isNew = false;
}
