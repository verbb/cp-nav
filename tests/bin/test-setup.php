<?php

declare(strict_types=1);

use craft\enums\CmsEdition;
use yii\console\ExitCode;

$pluginRoot = dirname(__DIR__, 2);
$envExample = $pluginRoot . '/.env.testing.example';
$envFile = $pluginRoot . '/.env.testing';

if (!file_exists($envFile) && file_exists($envExample)) {
    copy($envExample, $envFile);
    fwrite(STDOUT, "Created .env.testing from .env.testing.example.\n");
}

require dirname(__DIR__) . '/bootstrap.php';

try {
    $app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';
} catch (Throwable $e) {
    fwrite(STDERR, "Craft bootstrap failed: {$e->getMessage()}\n");
    exit(ExitCode::UNSPECIFIED_ERROR);
}

if ((getenv('ENVIRONMENT') ?: '') !== 'testing') {
    fwrite(STDERR, "Refusing setup outside ENVIRONMENT=testing.\n");
    exit(ExitCode::UNSPECIFIED_ERROR);
}

$runAction = static function(string $route, array $params = []) use ($app): int {
    return (int)$app->runAction($route, $params);
};

$driver = getenv('CRAFT_DB_DRIVER') ?: 'mysql';
$server = getenv('CRAFT_DB_SERVER') ?: '127.0.0.1';
$port = getenv('CRAFT_DB_PORT') ?: '3306';
$database = getenv('CRAFT_DB_DATABASE') ?: 'cpnav_test';
$user = getenv('CRAFT_DB_USER') ?: 'root';
$password = getenv('CRAFT_DB_PASSWORD');
$password = $password !== false ? $password : '';
$schema = getenv('CRAFT_DB_SCHEMA') ?: 'public';
$tablePrefix = getenv('CRAFT_DB_TABLE_PREFIX') ?: '';
$unixSocket = getenv('CRAFT_DB_UNIX_SOCKET') ?: '';

if ($unixSocket !== '' && $driver === 'mysql') {
    fwrite(STDOUT, "Detected CRAFT_DB_UNIX_SOCKET; skipping `craft setup/db`.\n");
} else {
    $dbExit = $runAction('setup/db', [
        'interactive' => 0,
        'driver' => $driver,
        'server' => $server,
        'port' => $port,
        'database' => $database,
        'user' => $user,
        'password' => $password,
        'schema' => $schema,
        'tablePrefix' => $tablePrefix,
    ]);

    if ($dbExit !== ExitCode::OK) {
        fwrite(STDERR, "Database setup failed. Check `.env.testing` CRAFT_DB_* values.\n");
        exit($dbExit);
    }
}

try {
    Craft::$app->getDb()->open();
} catch (Throwable $e) {
    fwrite(STDERR, "Database connectivity preflight failed: {$e->getMessage()}\n");
    exit(ExitCode::UNSPECIFIED_ERROR);
}

$tableNames = Craft::$app->getDb()->getSchema()->getTableNames();
$db = Craft::$app->getDb();

if ($tableNames) {
    fwrite(STDOUT, 'Dropping ' . count($tableNames) . " existing tables...\n");
}

try {
    if ($db->driverName === 'mysql') {
        $db->createCommand('SET FOREIGN_KEY_CHECKS = 0')->execute();
    }

    foreach ($tableNames as $tableName) {
        $db->createCommand()->dropTable($tableName)->execute();
    }
} finally {
    if ($db->driverName === 'mysql') {
        $db->createCommand('SET FOREIGN_KEY_CHECKS = 1')->execute();
    }
}

$siteUrl = getenv('PRIMARY_SITE_URL') ?: 'https://cpnav-test.test';
$installExit = $runAction('install/craft', [
    'interactive' => 0,
    'username' => 'admin',
    'email' => 'admin@example.test',
    'password' => 'password123',
    'siteName' => 'CP Nav Test',
    'siteUrl' => $siteUrl,
    'language' => 'en-US',
]);

if ($installExit !== ExitCode::OK) {
    fwrite(STDERR, "Craft install failed.\n");
    exit($installExit);
}

Craft::$app->setEdition(CmsEdition::Pro);

fwrite(STDOUT, "Test setup complete. Run `composer test`.\n");
exit(ExitCode::OK);
