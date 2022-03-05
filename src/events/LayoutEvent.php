<?php
namespace verbb\cpnav\events;

use verbb\cpnav\models\Layout;

use yii\base\Event;

class LayoutEvent extends Event
{
    // Properties
    // =========================================================================

    public Layout $layout;
    public bool $isNew = false;
}
