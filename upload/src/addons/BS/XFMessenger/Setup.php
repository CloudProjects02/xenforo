<?php

namespace BS\XFMessenger;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $sm = $this->schemaManager();
        foreach ($this->getAlterTables() as $tableName => $closures) {
            $sm->alterTable($tableName, $closures['up']);
        }
    }

    public function uninstallStep1()
    {
        $sm = $this->schemaManager();
        foreach ($this->getAlterTables() as $tableName => $closures) {
            $sm->alterTable($tableName, $closures['down']);
        }
    }

    protected function getAlterTables()
    {
        $tables = [];

        $tables['xf_conversation_master'] = [
            'up' => static function (Alter $table) {
                $table->addColumn('wallpaper_date', 'int')
                    ->unsigned()
                    ->setDefault(0);
                $table->addColumn('wallpaper_options', 'json')->nullable();
            },
            'down' => static function (Alter $table) {
                $table->dropColumns(['wallpaper_date', 'wallpaper_options']);
            }
        ];

        $tables['xf_conversation_user'] = [
            'up' => static function (Alter $table) {
                $table->addColumn('unread_count', 'int')->setDefault(0);

                $table->addColumn('room_wallpaper_date', 'int')
                    ->unsigned()
                    ->setDefault(0);
                $table->addColumn('room_wallpaper_options', 'json')->nullable();
            },
            'down' => static function (Alter $table) {
                $table->dropColumns(['unread_count', 'room_wallpaper_date', 'room_wallpaper_options']);
            }
        ];

        $tables['xf_conversation_message'] = [
            'up' => static function (Alter $table) {
                $table->addColumn('xfm_extra_data', 'json')->nullable();
                $table->addColumn('xfm_has_been_read', 'tinyint', 3)
                    ->unsigned()
                    ->setDefault(0);
                $table->addColumn('xfm_last_edit_date', 'int')
                    ->unsigned()
                    ->setDefault(0);
            },
            'down' => static function (Alter $table) {
                $table->dropColumns(['xfm_extra_data', 'xfm_has_been_read', 'xfm_last_edit_date']);
            }
        ];

        return $tables;
    }
}
