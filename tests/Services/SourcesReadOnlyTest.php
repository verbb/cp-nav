<?php

declare(strict_types=1);

use craft\elements\User;
use craft\events\ConfigEvent;
use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\nav\sources\NavSources;
use verbb\cpnav\nav\resolve\NavResolver;
use yii\base\Event;

describe('Sources read path', function() {
    it('does not write project config when building or resolving nav', function() {
        $projectConfig = Craft::$app->getProjectConfig();
        $projectConfig->readOnly = true;

        $writes = 0;
        $handler = function(ConfigEvent $event) use (&$writes) {
            if (str_starts_with($event->path ?? '', 'cp-nav')) {
                $writes++;
            }
        };

        Event::on(get_class($projectConfig), 'update', $handler);

        try {
            AdminUser::login();
            CpRequestContext::activate();

            $registry = CpNav::$plugin->getNavSources()->getTree(true);
            (new NavResolver())->resolve($registry, []);

            // Non-admin identity must also be read-only safe (#151).
            $editor = User::find()->admin(false)->status(null)->one();
            if ($editor) {
                Craft::$app->getUser()->setIdentity($editor);
                CpNav::$plugin->getNavSources()->getTree(true);
                (new NavResolver())->resolve($registry, []);
            }
        } finally {
            Event::off(get_class($projectConfig), 'update', $handler);
            $projectConfig->readOnly = false;
        }

        expect($writes)->toBe(0);
    });
});
