<?php

/*************************************************************************
 * Profile Views - XenConcept (c) 2017
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace xenConcept\ProfileViews;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

    // ################################ INSTALLATION ################################

    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() AS $tableName => $closure)
        {
            $sm->createTable($tableName, $closure);
        }
    }

    public function installStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlters(true) AS $tableName => $closure)
        {
            $sm->alterTable($tableName, $closure);
        }
    }


    public function installStep3()
    {
        foreach ($this->getDefaultWidgetSetup() AS $widgetKey => $widgetFn)
        {
            $widgetFn($widgetKey);
        }
    }

    // ################################ UPGRADE TO 2.0.2 ################################

    public function upgrade2000200Step1()
    {
        $sm = $this->schemaManager();

        $alters = $this->getAlters(true);
        //unset($alters['xf_user_profile']);

        foreach ($alters AS $tableName => $closure)
        {
            $sm->alterTable($tableName, $closure);
        }
    }

    // ################################ UPGRADE TO 2.0.4 Patch Level 1 ################################

    public function upgrade2000491Step1()
    {
        $sm = $this->schemaManager();

        if ($sm->tableExists('xc_profile_views'))
        {
            $sm->renameTable('xc_profile_views', 'xf_xc_profile_views');
        }
    }

    public function upgrade2000491Step2()
    {
        $db = $this->db();

        $userProfile = $db->fetchAll("
           SELECT *
           FROM xf_user_profile
           WHERE view_count != 0
        ");

        foreach ($userProfile AS $profile)
        {
            $db->update('xf_user', ['xc_pv_profile_view_count' => $profile['view_count']], 'user_id = ?', $profile['user_id']);
        }
    }

    public function upgrade2000491Step3()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_user_profile', function (Alter $table)
        {
            $table->dropColumns(['view_count']);
        });
    }

    // ################################ UNINSTALL ################################


    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        foreach (array_keys($this->getTables()) AS $tableName)
        {
            $sm->dropTable($tableName);
        }

        foreach ($this->getDefaultWidgetSetup() AS $widgetKey => $widgetFn)
        {
            $this->deleteWidget($widgetKey);
        }
    }

    public function uninstallStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlters(false) AS $tableName => $closure)
        {
            $sm->alterTable($tableName, $closure);
        }
    }

    public function uninstallStep3()
    {
        $this->deleteWidget('xc_profile_views_most_profile_viewed');
        $this->deleteWidget('xc_profile_views');
    }

    // ################################ TABLE / DATA DEFINITIONS ################################

    protected function getTables()
    {
        $tables = [];

        $tables['xf_xc_profile_views'] = function (Create $table)
        {
            $table->addColumn('view_id', 'int')->autoIncrement();
            $table->addColumn('visitor_user_id', 'int');
            $table->addColumn('profile_user_id', 'int');
            $table->addColumn('view_date', 'int');
        };

        return $tables;
    }

    protected function getAlters($isInstall)
    {
        $alters = [];

        if ($isInstall)
        {
            $alters['xf_user'] = function (Alter $table)
            {
                $table->addColumn('xc_pv_profile_view_count', 'int')->setDefault(0);
            };
            $alters['xf_user_privacy'] = function (Alter $table)
            {
                $table->addColumn('allow_view_users_who_viewed_profile', 'enum')->values(['everyone','members','followed','none'])->setDefault('everyone');
            };
        }
        else
        {
            $alters['xf_user'] = function (Alter $table)
            {
                $table->dropColumns(['xc_pv_profile_view_count']);
            };
            $alters['xf_user_privacy'] = function (Alter $table)
            {
                $table->dropColumns(['allow_view_users_who_viewed_profile']);
            };
        }

        return $alters;
    }

    protected function getDefaultWidgetSetup()
    {
        return [
            'xc_profile_views_most_profile_viewed' => function($key, array $options = [])
            {
                $options = array_replace([], $options);

                $this->createWidget(
                    $key,
                    'xc_profile_views_most',
                    [
                        'positions' => [
                            'forum_list_sidebar' => 60,
                        ],
                        'options' => $options
                    ]
                );
            },
            'xc_profile_views' => function($key, array $options = [])
            {
                $options = array_replace([], $options);

                $this->createWidget(
                    $key,
                    'xc_profile_views',
                    [
                        'positions' => [],
                        'options' => $options
                    ]
                );
            }
        ];
    }

    protected function insertNamedWidget($key, array $options = [])
    {
        $widgets = $this->getDefaultWidgetSetup();
        if (!isset($widgets[$key]))
        {
            throw new \InvalidArgumentException("Unknown widget '$key'");
        }

        $widgetFn = $widgets[$key];
        $widgetFn($key, $options);
    }
}