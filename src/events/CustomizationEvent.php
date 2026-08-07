<?php
namespace verbb\cpnav\events;

use verbb\cpnav\nav\customization\CustomizationNode;

use yii\base\Event;

class CustomizationEvent extends Event
{
    // Properties
    // =========================================================================

    public ?string $layoutUid = null;
    public ?CustomizationNode $node = null;
    public ?string $nodeKey = null;
}
