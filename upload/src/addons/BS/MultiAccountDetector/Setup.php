<?php

namespace BS\MultiAccountDetector;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Create;
use XF\Db\Schema\Alter;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /*
     * Installation
     */

    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $closure) {
            $sm->createTable($tableName, $closure);
        }
    }

    public function installStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $closure) {
            $sm->alterTable($tableName, $closure[0]);
        }
    }

    /*
     * Upgrade 1.0.0a
     */

    public function upgrade1000071Step1()
    {
        $this->schemaManager()->alterTable(
            'xf_user',
            function (Alter $table) {
                $table->addColumn('last_multi_account_check', 'int')->setDefault(0);
            }
        );
    }

    /*
     * Upgrade 1.3.0
     */
    public function upgrade1030070Step1()
    {
        $sm = $this->schemaManager();

        $sm->renameTable('xf_user_fingerprint', 'xf_mad_user_fingerprint');
        $sm->renameTable('xf_user_evercookie', 'xf_mad_user_evercookie');
        $sm->renameTable('xf_user_multi_account', 'xf_mad_user_multi_account');
    }

    public function upgrade1030070Step2()
    {
        $this->schemaManager()->alterTable(
            'xf_user',
            function (Alter $table) {
                $table->renameColumn('last_multi_account_check', 'mad_last_check');
            }
        );
    }

    /*
     * Upgrade 1.3.1a
     */
    public function upgrade1030171Step1()
    {
        $this->executeUpgradeQuery(
            '
            DELETE mult
            FROM xf_mad_user_multi_account AS mult
            LEFT JOIN xf_user AS user 
              ON (user.user_id = mult.user_id)
            WHERE user.user_id IS NULL
        ');
    }

    public function upgrade1030171Step2()
    {
        $this->executeUpgradeQuery(
            '
            DELETE evercookie
            FROM xf_mad_user_evercookie AS evercookie
            LEFT JOIN xf_user AS user 
              ON (user.user_id = evercookie.user_id)
            WHERE user.user_id IS NULL
        ');
    }

    public function upgrade1030171Step3()
    {
        $this->executeUpgradeQuery(
            '
            DELETE fingerprint
            FROM xf_mad_user_fingerprint AS fingerprint
            LEFT JOIN xf_user AS user 
              ON (user.user_id = fingerprint.user_id)
            WHERE user.user_id IS NULL
        ');
    }


    /*
     * Upgrade 1.4.0
     */
    public function upgrade1040070Step1()
    {
        $this->schemaManager()->alterTable(
            'xf_mad_user_fingerprint',
            static function (Alter $table) {
                $table->addColumn('is_pro', 'tinyint', 3)->setDefault(0);
            }
        );
    }

    public function upgrade1040070Step2()
    {
        $this->schemaManager()->alterTable(
            'xf_mad_user_multi_account',
            static function (Alter $table) {
                $table->addColumn('close_reason', 'text')->nullable();
            }
        );
    }

    /*
     * Final upgrade actions
     */
    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        if ($previousVersion < 1030171) {
            $this->app->repository('BS\MultiAccountDetector:MultiAccount')->rebuildMultiAccountsCache();
        }

        if ($previousVersion >= 1050070 && $previousVersion < 1050470) {
            $this->app->jobManager()->enqueueUnique(
                'mad1050470Upgrade',
                'BS\MultiAccountDetector:Upgrade1050470',
                [],
                false
            );
        }
    }

    /*
     * Check requirements before installation
     */
    public function checkRequirements(&$errors = [], &$warnings = [])
    {
        if (! $this->app->config('fullUnicode')) {
            $errors[] = "Add-on requires full unicode support. "
                . "Please follow the instructions from this page: https://xenforo.com/docs/xf2/unicode/";
        }
    }

    /*
     * Uninstall
     */
    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        foreach (array_keys($this->getTables()) as $tableName) {
            $sm->dropTable($tableName);
        }
    }

    public function uninstallStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $closure) {
            $sm->alterTable($tableName, $closure[1]);
        }
    }

    /*
     * Tables definition
     */
    protected function getTables()
    {
        $tables = [];

        $tables['xf_mad_user_fingerprint'] = static function (Create $table) {
            $table->addColumn('fingerprint_id', 'int')->autoIncrement();
            $table->addColumn('fingerprint', 'varbinary', 40);
            $table->addColumn('user_id', 'int');
            $table->addColumn('fingerprint_date', 'int')->setDefault(0);
            $table->addColumn('is_pro', 'tinyint', 3)->setDefault(0);
            $table->addKey('fingerprint');
            $table->addKey('user_id');
            $table->addKey('fingerprint_date');
        };

        $tables['xf_mad_user_evercookie'] = static function (Create $table) {
            $table->addColumn('evercookie_id', 'int')->autoIncrement();
            $table->addColumn('evercookie', 'varbinary', 32);
            $table->addColumn('user_id', 'int');
            $table->addColumn('evercookie_date', 'int')->setDefault(0);
            $table->addKey('evercookie');
            $table->addKey('user_id');
            $table->addKey('evercookie_date');
        };

        $tables['xf_mad_user_multi_account'] = static function (Create $table) {
            $table->addColumn('multi_account_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('multi_account_date', 'int')->setDefault(0);
            $table->addColumn('is_closed', 'tinyint', 3)->setDefault(0);
            $table->addColumn('close_reason', 'text')->nullable();
            $table->addKey('user_id');
            $table->addKey('multi_account_date');
            $table->addKey('is_closed');
        };

        return $tables;
    }

    protected function getAlterTables()
    {
        $tables = [];

        $tables['xf_user'] =
            [
                static function (Alter $table) {
                    $table->addColumn('mad_last_check', 'int')->setDefault(0);
                },
                static function (Alter $table) {
                    $table->dropColumns('mad_last_check');
                }
            ];

        return $tables;
    }
}