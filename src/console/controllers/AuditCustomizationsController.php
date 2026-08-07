<?php
namespace verbb\cpnav\console\controllers;

use verbb\cpnav\upgrade\CustomizationUpgradeService;

use craft\console\Controller;
use craft\helpers\Console;

use yii\console\ExitCode;

class AuditCustomizationsController extends Controller
{
    // Properties
    // =========================================================================

    public ?string $layoutUid = null;
    /** Remove stale canonical customization keys from project config (staging / allowAdminChanges). */
    public bool $fix = false;


    // Public Methods
    // =========================================================================

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['layoutUid', 'fix']);
    }

    public function optionAliases(): array
    {
        return [
            'l' => 'layoutUid',
        ];
    }

    public function actionIndex(): int
    {
        $service = new CustomizationUpgradeService();
        $results = $service->auditLayouts($this->layoutUid);

        if ($results === []) {
            $this->stderr("No layouts found to audit.\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($results as $result) {
            $this->stdout(sprintf("%s (%s)\n", $result['layoutName'], $result['layoutUid']));

            if ($result['stale'] === []) {
                $this->stdout("  stale customization keys: none\n");
            } else {
                $this->stdout('  stale customization keys: ' . implode(', ', $result['stale']) . "\n");
            }

            $missingCount = count($result['missing']);
            $this->stdout("  nav source keys without customization: {$missingCount} (default-position insert at render)\n");

            if ($this->fix && $result['stale'] !== []) {
                $removed = $service->purgeStaleKeys($result['layoutUid'], $result['stale']);
                $this->stdout('  purged: ' . implode(', ', $removed) . "\n", Console::FG_YELLOW);
            }
        }

        return ExitCode::OK;
    }
}
