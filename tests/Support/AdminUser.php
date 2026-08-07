<?php

declare(strict_types=1);

namespace Tests\Support;

use craft\elements\User;
use Craft;

final class AdminUser
{
    private static ?User $admin = null;

    public static function login(): User
    {
        $admin = self::findAdmin();
        Craft::$app->getUser()->setIdentity($admin);

        return $admin;
    }

    public static function findAdmin(): User
    {
        if (self::$admin) {
            return self::$admin;
        }

        $admin = User::find()->admin(true)->status(null)->one();
        if (!$admin) {
            throw new \RuntimeException('No admin user found in test database.');
        }

        return self::$admin = $admin;
    }
}
