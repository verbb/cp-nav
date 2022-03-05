<?php
namespace verbb\cpnav\events;

use yii\base\Event;

class ReorderLayoutsEvent extends Event
{
    public array $layoutIds = [];
}
