<?php
// Normal Craft login, CSRF and CP rendering against the owned disposable application.
require __DIR__ . '/bootstrap.php';
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
$app->setEdition(\craft\enums\CmsEdition::Pro);
$app->run();
