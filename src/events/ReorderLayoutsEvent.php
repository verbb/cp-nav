<?php
namespace verbb\cpnav\events;

use yii\base\Event;

class ReorderLayoutsEvent extends Event
{
    // Properties
    // =========================================================================

    public array $layoutIds = [];
}
