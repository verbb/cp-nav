<?php
namespace verbb\cpnav\events;

use yii\base\Event;

class LayoutEvent extends Event
{
    // Properties
    // =========================================================================

    public $layout;

    public $isNew = false;
}
