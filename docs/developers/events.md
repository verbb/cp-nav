# Events
Control Panel Nav provides a collection of events for extending its functionality. Modules and plugins can register event listeners, typically in their `init()` methods, to modify Control Panel Nav’s behaviour.

## Navigation Events

### The `modifyResolvedNav` Event

The event that is triggered after Craft and plugin menu items have been combined with the selected layout's saved customisations, before filtering for the signed-in person's access. It supplies `layoutUid` and a flat array of `ResolvedNavNode` objects in `resolvedNodes`. Each node's `parentKey` identifies its parent, or is `null` for a top-level item.

For example, this listener changes Dashboard’s displayed label to Workspace across layouts:

```php
use verbb\cpnav\events\ModifyResolvedNavEvent;
use verbb\cpnav\nav\resolve\NavResolver;
use verbb\cpnav\nav\resolve\ResolvedNavNode;
use yii\base\Event;

Event::on(NavResolver::class, NavResolver::EVENT_MODIFY_RESOLVED_NAV, function(ModifyResolvedNavEvent $event) {
    foreach ($event->resolvedNodes as $index => $node) {
        if ($node->key !== 'craft:dashboard') {
            continue;
        }

        $event->resolvedNodes[$index] = new ResolvedNavNode(
            key: $node->key,
            label: 'Workspace',
            url: $node->url,
            sort: $node->sort,
            parentKey: $node->parentKey,
            source: $node->source,
            enabled: $node->enabled,
            icon: $node->icon,
            customIcon: $node->customIcon,
            newWindow: $node->newWindow,
            isOrphan: $node->isOrphan,
        );
    }
});
```

The node's properties are readonly, so the handler creates a replacement node and assigns it back to the event's array. It preserves the destination, position, visibility, and icon. This changes the resolved menu without saving a new label to Project Config, and takes precedence over a label entered in the builder.

Reload the control panel with an account that can see Dashboard. Its link should read Workspace and still open Dashboard. Remove the handler and reload to return to the configured label. A hidden or unavailable Dashboard remains hidden; this event does not grant access.

Use [Node Keys](/developers/node-keys) to identify other items. To limit an adjustment to one layout, compare `$event->layoutUid` with that layout's UID before changing the array. Avoid writing customisations from this handler: it runs while navigation is being resolved, including during ordinary page views.

<span id="customization-events"></span>

## Customisation Events

Customisation events report writes through `NavCustomization`; they do not run once for every item displayed in the sidebar. These events do not support cancellation.

### The `afterSaveNode` Event
The event that is triggered after a single navigation customisation is saved. The event supplies `layoutUid`, `nodeKey`, and the saved `CustomizationNode` in `node`.

```php
use verbb\cpnav\events\CustomizationEvent;
use verbb\cpnav\nav\customization\NavCustomization;
use yii\base\Event;

Event::on(NavCustomization::class, NavCustomization::EVENT_AFTER_SAVE_NODE, function(CustomizationEvent $event) {
    $node = $event->node;
    \Craft::info("Saved navigation item {$event->nodeKey} in layout {$event->layoutUid}.", 'cp-nav-project');
});
```

### The `afterRemoveNode` Event
The event that is triggered after a single navigation customisation is removed. The event supplies `layoutUid` and `nodeKey`; `node` is `null`.

```php
use verbb\cpnav\events\CustomizationEvent;
use verbb\cpnav\nav\customization\NavCustomization;
use yii\base\Event;

Event::on(NavCustomization::class, NavCustomization::EVENT_AFTER_REMOVE_NODE, function(CustomizationEvent $event) {
    \Craft::info("Removed navigation item {$event->nodeKey} from layout {$event->layoutUid}.", 'cp-nav-project');
});
```

### The `afterSetNodes` Event
The event that is triggered after a complete navigation customisation set is written, including reorder and reset operations. The event supplies `layoutUid`; `node` and `nodeKey` are `null`.

```php
use verbb\cpnav\events\CustomizationEvent;
use verbb\cpnav\nav\customization\NavCustomization;
use yii\base\Event;

Event::on(NavCustomization::class, NavCustomization::EVENT_AFTER_SET_NODES, function(CustomizationEvent $event) {
    \Craft::info("Updated navigation items in layout {$event->layoutUid}.", 'cp-nav-project');
});
```

The examples write to Craft’s application logs in `storage/logs`. Your logging configuration must retain info-level messages for the `cp-nav-project` category. A reorder or reset uses `afterSetNodes`, so an `afterSaveNode` handler alone does not cover every navigation change.

## Layout Events

Layout save and delete events supply the affected layout in `$event->layout`. Save events also supply `$event->isNew`. Reorder events supply `$event->layoutIds`. These events do not support cancellation; changing `layoutIds` in the before-event does not change the order the service processes.

### The `beforeSaveLayout` Event
The event that is triggered before layout metadata is validated and written to Project Config.

```php
use verbb\cpnav\events\LayoutEvent;
use verbb\cpnav\services\Layouts;
use yii\base\Event;

Event::on(Layouts::class, Layouts::EVENT_BEFORE_SAVE_LAYOUT, function(LayoutEvent $event) {
    $layout = $event->layout;
    $isNew = $event->isNew;
    \Craft::info("beforeSaveLayout: {$layout->name} ({$layout->uid}).", 'cp-nav-project');
});
```

### The `afterSaveLayout` Event
The event that is triggered after a Project Config change has been applied to a layout record. This can also run when Project Config is applied, and a metadata update can produce multiple callbacks. Make external work safe to process the same layout more than once.

```php
use verbb\cpnav\events\LayoutEvent;
use verbb\cpnav\services\Layouts;
use yii\base\Event;

Event::on(Layouts::class, Layouts::EVENT_AFTER_SAVE_LAYOUT, function(LayoutEvent $event) {
    $layout = $event->layout;
    $isNew = $event->isNew;
    \Craft::info("afterSaveLayout: {$layout->name} ({$layout->uid}).", 'cp-nav-project');
});
```

### The `beforeDeleteLayout` Event
The event that is triggered before removal of a layout is requested from Project Config.

```php
use verbb\cpnav\events\LayoutEvent;
use verbb\cpnav\services\Layouts;
use yii\base\Event;

Event::on(Layouts::class, Layouts::EVENT_BEFORE_DELETE_LAYOUT, function(LayoutEvent $event) {
    $layout = $event->layout;
    \Craft::info("beforeDeleteLayout: {$layout->name} ({$layout->uid}).", 'cp-nav-project');
});
```

### The `beforeApplyLayoutDelete` Event
The event that is triggered before a Project Config deletion is applied to a layout record.

```php
use verbb\cpnav\events\LayoutEvent;
use verbb\cpnav\services\Layouts;
use yii\base\Event;

Event::on(Layouts::class, Layouts::EVENT_BEFORE_APPLY_LAYOUT_DELETE, function(LayoutEvent $event) {
    $layout = $event->layout;
    \Craft::info("beforeApplyLayoutDelete: {$layout->name} ({$layout->uid}).", 'cp-nav-project');
});
```

### The `afterDeleteLayout` Event
The event that is triggered after a layout record has been deleted.

```php
use verbb\cpnav\events\LayoutEvent;
use verbb\cpnav\services\Layouts;
use yii\base\Event;

Event::on(Layouts::class, Layouts::EVENT_AFTER_DELETE_LAYOUT, function(LayoutEvent $event) {
    $layout = $event->layout;
    \Craft::info("afterDeleteLayout: {$layout->name} ({$layout->uid}).", 'cp-nav-project');
});
```

### The `beforeReorderLayouts` Event
The event that is triggered before the service processes a layout reorder.

```php
use verbb\cpnav\events\ReorderLayoutsEvent;
use verbb\cpnav\services\Layouts;
use yii\base\Event;

Event::on(Layouts::class, Layouts::EVENT_BEFORE_REORDER_LAYOUTS, function(ReorderLayoutsEvent $event) {
    $layoutIds = $event->layoutIds;
    \Craft::info('Layout order: ' . implode(', ', $layoutIds), 'cp-nav-project');
});
```

### The `afterReorderLayouts` Event
The event that is triggered after the service processes a layout reorder.

```php
use verbb\cpnav\events\ReorderLayoutsEvent;
use verbb\cpnav\services\Layouts;
use yii\base\Event;

Event::on(Layouts::class, Layouts::EVENT_AFTER_REORDER_LAYOUTS, function(ReorderLayoutsEvent $event) {
    $layoutIds = $event->layoutIds;
    \Craft::info('Layout order: ' . implode(', ', $layoutIds), 'cp-nav-project');
});
```
