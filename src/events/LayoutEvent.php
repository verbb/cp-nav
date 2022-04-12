<?php
namespace verbb\cpnav\events;

use verbb\cpnav\models\Layout;

use yii\base\Event;

class LayoutEvent extends Event
{
    // Properties
    // =========================================================================

    public ?Layout $layout = null;
    public bool $isNew = false;
}
