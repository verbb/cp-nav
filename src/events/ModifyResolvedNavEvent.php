<?php
namespace verbb\cpnav\events;

use yii\base\Event;

class ModifyResolvedNavEvent extends Event
{
    // Properties
    // =========================================================================

    public array $resolvedNodes = [];
    public ?string $layoutUid = null;
}
