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
    public bool $force = false;
    public ?string $layoutUid = null;


    // Public Methods
    // =========================================================================

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun', 'force', 'layoutUid']);
    }

    public function optionAliases(): array
    {
        return [
            'd' => 'dryRun',
            'f' => 'force',
            'l' => 'layoutUid',
        ];
    }

    public function actionIndex(): int
    {
        $service = new CustomizationUpgradeService();
        $results = $service->upgradeLayouts($this->layoutUid, $this->dryRun, $this->force);

        if ($results === []) {
            $this->stderr("No layouts found to migrate.\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $migrated = 0;
        $skipped = 0;

        foreach ($results as $result) {
            $mode = $this->dryRun ? '[dry-run] ' : '';
            $status = $result['status'] ?? 'migrated';
            $this->stdout(sprintf(
                "%s%s (%s): %d customization nodes [%s]\n",
                $mode,
                $result['layoutName'],
                $result['layoutUid'],
                $result['nodeCount'],
                $status,
            ));

            if (str_starts_with($status, 'skipped_')) {
                $skipped++;
            } else {
                $migrated++;
            }
        }

        if ($skipped > 0 && !$this->force) {
            $this->stdout("Skipped {$skipped} layout(s) with existing customizations or no legacy rows. Use --force to replace nonempty v6 layouts.\n");
        }

        if ($this->dryRun) {
            $this->stdout("Dry run complete — no project config changes written.\n");
        } else {
            $this->stdout("Migrated/replaced {$migrated} layout(s).\n");
        }

        return ExitCode::OK;
    }
}
