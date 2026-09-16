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

it('rejects a symlink outside the icon root and serves an allowed icon to a CP user', function() {
    $dir = $this->fixtureIcons();
    $outside = dirname($dir) . '/outside-' . basename($dir) . '.svg';
    file_put_contents($outside, '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $this->onCleanup(fn() => unlink($outside));
    expect(symlink($outside, $dir . '/escape.svg'))->toBeTrue();
    $icons = \verbb\cpnav\CpNav::$plugin->getStaticIcons();
    expect($icons->resolveAbsolutePath('escape.svg'))->toBeNull();
    expect($icons->resolveAbsolutePath('missing.svg'))->toBeNull();
    Craft::$app->getUser()->setIdentity($this->fixtureEditor());
    // Catalog management is admin-only; serving an allowed sidebar icon is not.
    expect(fn() => \Tests\Support\ActionRequest::dispatch('static-icons/index'))->toThrow(\yii\web\ForbiddenHttpException::class);
    $request = Craft::$app->getRequest();
    $request->setQueryParams(['file' => 'brand/mark.svg']);
    $controller = new \verbb\cpnav\controllers\StaticIconsController('static-icons', \verbb\cpnav\CpNav::$plugin);
    $response = $controller->runAction('view');
    self::assertSame('image/svg+xml', explode(';', $response->headers->get('Content-Type'))[0], json_encode($response->headers->toArray()));
    rewind($response->stream[0]);
    expect(stream_get_contents($response->stream[0]))->toBe(file_get_contents($dir . '/brand/mark.svg'));
    fclose($response->stream[0]);
    $request->setQueryParams(['file' => 'escape.svg']);
    expect(fn() => $controller->runAction('view'))->toThrow(\yii\web\NotFoundHttpException::class);
});
