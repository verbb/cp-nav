<?php

declare(strict_types=1);

namespace Tests\General;

use Craft;
use craft\elements\User;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\web\twig\variables\Cp;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\cpnav\CpNav;
use verbb\cpnav\models\Layout;
use yii\base\Event;

abstract class TestCase extends BaseTestCase
{
    private array $cleanups = [];
    private array $savedState;

    protected function setUp(): void
    {
        parent::setUp();
        self::assertNotNull(Craft::$app, 'The owned Craft test application must be bootstrapped.');
        $pc = Craft::$app->getProjectConfig();
        $this->nextConfigRequest();
        $this->savedState = [
            'config' => $pc->get('cp-nav'), 'readOnly' => $pc->readOnly,
            'response' => Craft::$app->getResponse(), 'request' => Craft::$app->getRequest(), 'identity' => Craft::$app->getUser()->getIdentity(),
            'language' => Craft::$app->language, 'edition' => Craft::$app->getEdition(),
            'general' => clone Craft::$app->getConfig()->getGeneral(),
            'settings' => CpNav::$plugin->getSettings()->getAttributes(),
        ];
        $pc->readOnly = false;
        AdminUser::login();
        CpRequestContext::activate();
        CpNav::$plugin->getNavSources()->invalidate();
    }

    protected function tearDown(): void
    {
        try {
            // Explicit cleanup works for migration tests too: MySQL DDL cannot be rolled back.
            Craft::$app->getProjectConfig()->readOnly = false;
            foreach (array_reverse($this->cleanups) as $cleanup) {
                $cleanup();
            }
            $pc = Craft::$app->getProjectConfig();
            $pc->muteEvents = false;
            $this->nextConfigRequest();
            // Craft dispatches handlers at the changed path; restoring only the cp-nav
            // parent would restore JSON without updating layout records.
            $savedLayouts = $this->savedState['config']['layouts'] ?? [];
            foreach (array_unique(array_merge(array_keys($pc->get('cp-nav.layouts') ?? []), array_keys($savedLayouts))) as $uid) {
                $pc->set('cp-nav.layouts.' . $uid, $savedLayouts[$uid] ?? null, force: true);
            }
            $pc->set('cp-nav', $this->savedState['config']);
            $this->nextConfigRequest();
            CpNav::$plugin->getSettings()->setAttributes($this->savedState['settings'], false);
            CpNav::$plugin->getStaticIcons()->resetCache();
            CpNav::$plugin->getNavSources()->invalidate();
        } finally {
            Craft::$app->set('response', $this->savedState['response']);
            Craft::$app->set('request', $this->savedState['request']);
            Craft::$app->getUser()->setIdentity($this->savedState['identity']);
            Craft::$app->language = $this->savedState['language'];
            Craft::$app->setEdition($this->savedState['edition']);
            Craft::$app->getConfig()->getGeneral()->setAttributes($this->savedState['general']->getAttributes(), false);
            Craft::$app->getProjectConfig()->readOnly = $this->savedState['readOnly'];
            parent::tearDown();
        }
    }

    public function nextConfigRequest(): void
    {
        // Craft deduplicates parent-path callbacks within a request. Persist and
        // reset its request state when a scenario represents another admin action.
        $pc = Craft::$app->getProjectConfig();
        $pc->saveModifiedConfigData();
        $pc->reset();
    }

    public function onCleanup(callable $callback): void
    {
        $this->cleanups[] = $callback;
    }

    public function fixtureLayout(array $attributes = []): Layout
    {
        $layout = new Layout(array_merge(['name' => 'Test layout', 'permissions' => []], $attributes));
        self::assertTrue(CpNav::$plugin->getLayouts()->saveLayout($layout));
        self::assertNotNull($layout->id);
        $this->nextConfigRequest();
        $this->onCleanup(fn() => CpNav::$plugin->getLayouts()->deleteLayout($layout));
        return $layout;
    }

    public function fixtureEditor(array $permissions = ['accessCp']): User
    {
        $user = new User(['username' => 'editor-' . StringHelper::randomString(10), 'email' => StringHelper::randomString(10) . '@example.test']);
        self::assertTrue(Craft::$app->getElements()->saveElement($user), json_encode($user->getErrors()));
        self::assertTrue(Craft::$app->getUserPermissions()->saveUserPermissions($user->id, $permissions));
        $this->onCleanup(fn() => Craft::$app->getElements()->deleteElement($user, true));
        return $user;
    }

    public function fixtureProvider(callable $handler): void
    {
        Event::on(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, $handler);
        $this->onCleanup(fn() => Event::off(Cp::class, Cp::EVENT_REGISTER_CP_NAV_ITEMS, $handler));
        CpNav::$plugin->getNavSources()->invalidate();
    }

    public function fixtureIcons(): string
    {
        $dir = Craft::$app->getPath()->getTempPath() . '/cpnav-icons-' . StringHelper::UUID();
        FileHelper::createDirectory($dir . '/brand');
        file_put_contents($dir . '/brand/mark.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M0 0h16v16z"/></svg>');
        CpNav::$plugin->getSettings()->iconsPath = $dir;
        CpNav::$plugin->getStaticIcons()->resetCache();
        $this->onCleanup(fn() => FileHelper::removeDirectory($dir));
        return $dir;
    }
}
