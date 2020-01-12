<?php
namespace verbb\cpnav\events;

use yii\base\Event;

class NavigationEvent extends Event
{
    // Properties
    // =========================================================================

    public $layout;

    public $isNew = false;
}
