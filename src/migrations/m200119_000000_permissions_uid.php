<?php
namespace verbb\cpnav\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;
use craft\helpers\Json;

use Throwable;

class m200119_000000_permissions_uid extends Migration
{
    public function safeUp(): bool
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
                    } catch (Throwable) {
                        continue;
                    }
                }
            }

            if ($newPermissions) {
                $this->update('{{%cpnav_layout}}', ['permissions' => Json::encode($newPermissions)], ['id' => $layout['id']]);
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m200119_000000_permissions_uid cannot be reverted.\n";
        return false;
    }
}

