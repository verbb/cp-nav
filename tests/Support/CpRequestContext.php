<?php

declare(strict_types=1);

namespace Tests\Support;

use Craft;
use craft\web\Request as WebRequest;

final class CpRequestContext
{
    public static function activate(string $path = 'dashboard'): void
    {
        /** @var WebRequest $request */
        $request = Craft::createObject(WebRequest::class);
        $request->setIsCpRequest(true);
        $request->setPathInfo($path);
        Craft::$app->set('request', $request);
    }
}
