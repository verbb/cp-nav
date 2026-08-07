<?php

declare(strict_types=1);

use craft\db\Query;
use Tests\Support\CpNavTestPlugin;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Support/CpNavTestPlugin.php';

try {
    $app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';
} catch (Throwable $e) {
    throw new RuntimeException(
        'Craft bootstrap failed for tests. Run `composer test:setup` and verify `.env.testing` CRAFT_DB_* values.',
        0,
        $e
    );
}

if (!class_exists(Craft::class) || !Craft::$app) {
    throw new RuntimeException('Craft application failed to bootstrap for integration tests.');
}

if ((getenv('ENVIRONMENT') ?: '') !== 'testing') {
    throw new RuntimeException('Refusing to run tests outside ENVIRONMENT=testing.');
}

$db = Craft::$app->getDb();
if (!$db->tableExists('{{%plugins}}')) {
    throw new RuntimeException(
        'Testing database is not installed yet. Run `composer test:setup` first.'
    );
}

CpNavTestPlugin::ensureInstalledAndMigrated();
