<?php

use craft\helpers\App;

return [
    'id' => App::env('CRAFT_APP_ID') ?: 'CraftCMS-CpNavTests',
    'components' => [
        'projectConfig' => function() {
            $config = craft\helpers\App::projectConfigConfig();
            // Tests control YAML writes explicitly.
            $config['writeYamlAutomatically'] = false;

            return Craft::createObject($config);
        },
    ],
];
