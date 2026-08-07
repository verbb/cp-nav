<?php
namespace verbb\cpnav\console\controllers;

use verbb\cpnav\upgrade\CustomizationUpgradeService;

use craft\console\Controller;

use yii\console\ExitCode;

class MigrateCustomizationsController extends Controller
{
    // Properties
    // =========================================================================

    public bool $dryRun = false;
    public ?string $layoutUid = null;


    // Public Methods
    // =========================================================================

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun', 'layoutUid']);
    }

    public function optionAliases(): array
    {
        return [
            'd' => 'dryRun',
            'l' => 'layoutUid',
        ];
    }

    public function actionIndex(): int
    {
        $service = new CustomizationUpgradeService();
        $results = $service->upgradeLayouts($this->layoutUid, $this->dryRun);

        if ($results === []) {
            $this->stderr("No layouts found to migrate.\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($results as $result) {
            $mode = $this->dryRun ? '[dry-run] ' : '';
            $this->stdout(sprintf(
                "%s%s (%s): %d customization nodes\n",
                $mode,
                $result['layoutName'],
                $result['layoutUid'],
                $result['nodeCount'],
            ));
        }

        if ($this->dryRun) {
            $this->stdout("Dry run complete — no project config changes written.\n");
        }

        return ExitCode::OK;
    }
}
