# Events

## Navigation Events

### The `modifyResolvedNav` event

Fired after nav sources and customizations are merged, **before** permission filtering. Use this to adjust the tree programmatically.

```php
use verbb\cpnav\events\ModifyResolvedNavEvent;
use verbb\cpnav\nav\resolve\NavResolver;
use yii\base\Event;

Event::on(NavResolver::class, NavResolver::EVENT_MODIFY_RESOLVED_NAV, function(ModifyResolvedNavEvent $event) {
    $resolvedNodes = $event->resolvedNodes;
    $layoutUid = $event->layoutUid;
    // ...
});
```

## Customization Events

### The `afterSaveNode` event

Fired after a customization node is saved for a layout.

```php
use verbb\cpnav\events\CustomizationEvent;
use verbb\cpnav\nav\customization\NavCustomization;
use yii\base\Event;

Event::on(NavCustomization::class, NavCustomization::EVENT_AFTER_SAVE_NODE, function(CustomizationEvent $event) {
    $layoutUid = $event->layoutUid;
    $node = $event->node;
    $nodeKey = $event->nodeKey;
    // ...
});
```

### The `afterRemoveNode` event

Fired after a customization node is removed.

```php
use verbb\cpnav\events\CustomizationEvent;
use verbb\cpnav\nav\customization\NavCustomization;
use yii\base\Event;

Event::on(NavCustomization::class, NavCustomization::EVENT_AFTER_REMOVE_NODE, function(CustomizationEvent $event) {
    $layoutUid = $event->layoutUid;
    $nodeKey = $event->nodeKey;
    // ...
});
```

### The `afterSetNodes` event

Fired after a full customization node set is written for a layout (e.g. reorder / reset flows).

```php
use verbb\cpnav\events\CustomizationEvent;
use verbb\cpnav\nav\customization\NavCustomization;
use yii\base\Event;

Event::on(NavCustomization::class, NavCustomization::EVENT_AFTER_SET_NODES, function(CustomizationEvent $event) {
    $layoutUid = $event->layoutUid;
    // ...
});
```

## Layout Events

Layout CRUD still uses `verbb\cpnav\services\Layouts` events:

- `EVENT_BEFORE_SAVE_LAYOUT` / `EVENT_AFTER_SAVE_LAYOUT`
- `EVENT_BEFORE_DELETE_LAYOUT` / `EVENT_BEFORE_APPLY_LAYOUT_DELETE` / `EVENT_AFTER_DELETE_LAYOUT`
- `EVENT_BEFORE_REORDER_LAYOUTS` / `EVENT_AFTER_REORDER_LAYOUTS`

```php
use verbb\cpnav\events\LayoutEvent;
use verbb\cpnav\services\Layouts;
use yii\base\Event;

Event::on(Layouts::class, Layouts::EVENT_AFTER_SAVE_LAYOUT, function(LayoutEvent $event) {
    $layout = $event->layout;
    // ...
});
```
