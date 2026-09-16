<?php

namespace Tests\Support;

use Craft;
use craft\web\Request;
use craft\web\Response;
use verbb\cpnav\CpNav;

final class ActionRequest extends Request
{
    public string $method = 'POST';

    public function getMethod(): string
    {
        return $this->method;
    }

    public static function dispatch(string $route, array $body = [], string $method = 'POST', bool $validCsrf = true): Response
    {
        Craft::$app->getProjectConfig()->saveModifiedConfigData();
        Craft::$app->getProjectConfig()->reset();
        $request = Craft::createObject([
            'class' => self::class, 'method' => $method,
            'enableCsrfCookie' => true, 'cookieValidationKey' => getenv('CRAFT_SECURITY_KEY'),
        ]);
        $request->setIsCpRequest(true);
        $request->setPathInfo('cp-nav');
        $request->getHeaders()->set('Accept', 'application/json');
        Craft::$app->set('request', $request);
        Craft::$app->set('response', new Response());
        $body[$request->csrfParam] = $validCsrf ? $request->getCsrfToken() : 'invalid-token';
        $request->setBodyParams($body);
        // Real controller dispatch runs beforeAction, CSRF, authentication and the action.
        [$controller, $action] = explode('/', $route, 2);
        $class = 'verbb\\cpnav\\controllers\\' . str_replace(' ', '', ucwords(str_replace('-', ' ', $controller))) . 'Controller';
        return (new $class($controller, CpNav::$plugin))->runAction($action);
    }
}
