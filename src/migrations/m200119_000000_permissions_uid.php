<?php
namespace verbb\cpnav\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use craft\helpers\Component as ComponentHelper;
use craft\helpers\StringHelper;

class m200119_000000_permissions_uid extends Migration
{
    public function safeUp()
    {
        $layouts = (new Query())
            ->from('{{%cpnav_layout}}')
            ->all();

        foreach ($layouts as $layout) {
            $permissions = Json::decodeIfJson($layout['permissions']);

            $newPermissions = [];

            if (is_array($permissions)) {
                foreach ($permissions as $permission) {
                    try {
                        $newPermission = Db::uidById(Table::USERGROUPS, (int)$permission) ?? '';

                        if ($newPermission) {
                            $newPermissions[] = $newPermission;
                        }
                    } catch (\Throwable $e) {
                        continue;
                    }
                }
            }

            if ($newPermissions) {
                $this->update('{{%cpnav_layout}}', ['permissions' => Json::encode($newPermissions)], ['id' => $layout['id']]);
            }
        }
    }

    public function safeDown()
    {
        echo "m200119_000000_permissions_uid cannot be reverted.\n";
        return false;
    }
}

