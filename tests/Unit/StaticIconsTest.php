<?php

declare(strict_types=1);

use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use verbb\cpnav\CpNav;
use verbb\cpnav\helpers\CustomIcon;
use verbb\cpnav\models\Settings;

describe('StaticIcons', function() {
    it('scans the icons folder and rejects traversal', function() {
        $dir = Craft::$app->getPath()->getTempPath() . '/cpnav-icons-' . StringHelper::UUID();
        FileHelper::createDirectory($dir);
        FileHelper::createDirectory($dir . '/brand');
        file_put_contents($dir . '/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        file_put_contents($dir . '/brand/mark.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        file_put_contents($dir . '/readme.txt', 'ignore');

        /** @var Settings $settings */
        $settings = CpNav::$plugin->getSettings();
        $previous = $settings->iconsPath;
        $settings->iconsPath = $dir;
        CpNav::$plugin->getStaticIcons()->resetCache();

        try {
            $catalog = CpNav::$plugin->getStaticIcons()->getCatalog();
            $values = array_column($catalog, 'value');
            expect($values)->toContain('logo.svg', 'brand/mark.svg')
                ->and($values)->not->toContain('readme.txt');

            expect(CpNav::$plugin->getStaticIcons()->resolveAbsolutePath('logo.svg'))
                ->toBe(realpath($dir . '/logo.svg'));
            expect(CpNav::$plugin->getStaticIcons()->resolveAbsolutePath('../logo.svg'))
                ->toBeNull();
            expect(CustomIcon::normalizeStored('[123]'))->toBeNull();
            expect(CustomIcon::normalizeStored('brand/mark.svg'))->toBe('brand/mark.svg');
        } finally {
            $settings->iconsPath = $previous;
            CpNav::$plugin->getStaticIcons()->resetCache();
            FileHelper::removeDirectory($dir);
        }
    });
});
