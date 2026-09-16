<?php

namespace Tests\Support;

use Craft;
use craft\events\ConfigEvent;
use craft\services\ProjectConfig;
use yii\base\Event;

final class ConfigWrites
{
    public array $paths = [];
    private \Closure $handler;
    private string $class;
    private const EVENTS = [ProjectConfig::EVENT_ADD_ITEM, ProjectConfig::EVENT_UPDATE_ITEM, ProjectConfig::EVENT_REMOVE_ITEM];

    public function __construct()
    {
        $this->class = get_class(Craft::$app->getProjectConfig());
        $this->handler = function(ConfigEvent $event): void {
            if (str_starts_with($event->path, 'cp-nav')) {
                $this->paths[] = [$event->name, $event->path];
            }
        };
        foreach (self::EVENTS as $name) {
            Event::on($this->class, $name, $this->handler);
        }
    }

    public function close(): void
    {
        foreach (self::EVENTS as $name) {
            Event::off($this->class, $name, $this->handler);
        }
    }
}
