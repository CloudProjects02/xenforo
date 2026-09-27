<?php

namespace Andrew\ModeratorPanel;

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

    public function installStep1()
    {
        $sm = $this->schemaManager();
        $sm->createTable('xf_andrew_mp_user_note', function(Create $table)
        {
            $table->addColumn('note_id', 'int')->autoIncrement();
            $table->addColumn('note_user_id', 'int')->nullable(true)->setDefault(null);
            $table->addColumn('user_id', 'int')->nullable(true)->setDefault(null);
            $table->addColumn('username','varchar', 250)->nullable(true)->setDefault(null);
            $table->addColumn('create_date', 'int',10)->nullable(true)->setDefault(\XF::$time);
            $table->addColumn('message', 'mediumtext')->nullable(true)->setDefault(null);
            $table->addColumn('edit_user_id', 'int')->nullable(true)->setDefault(null);
            $table->addColumn('last_edit_date', 'int',10)->setDefault(0);
            $table->addColumn('is_privileged', 'bool')->setDefault(0);
            $table->addPrimaryKey('note_id');
        });
    }

    public function installStep2()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('andrew_user_note_count', 'int')->setDefault(0);
            $table->addColumn('andrew_privileged_user_note_count', 'int')->setDefault(0);
            $table->addColumn('andrew_reg_country','varchar', 250)->nullable(true)->setDefault(null);
        });
    }

    public function installStep3()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_warning', function(Alter $table)
        {
            $table->addColumn('andrew_mp_thread_id', 'int')->setDefault(0);
        });
    }

    public function installStep4()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_user_ignored', function(Alter $table)
        {
            $table->addColumn('andrew_forced', 'int')->setDefault(0);
            $table->addColumn('andrew_forced_user_id', 'int')->setDefault(0);
            $table->addColumn('andrew_forced_datetime', 'int')->setDefault(\XF::$time);
        });
    }

    public function installStep5()
    {
        $sm = $this->schemaManager();
        $sm->createTable('xf_andrew_mp_user_note_category', function (Create $table)
        {

            $table->addColumn('note_category_id', 'int')->autoIncrement();
            $table->addColumn('title','varchar', 250)->nullable(true)->setDefault(null);
            $table->addColumn('use_count', 'int')->nullable(false)->setDefault(0);
            $table->addColumn('last_used_date', 'int')->nullable(true)->setDefault(null);
            $table->addPrimaryKey('note_category_id');

        });
    }

    public function installStep6()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_andrew_mp_user_note', function(Alter $table)
        {
            $table->addColumn('note_category_id', 'int')->setDefault(0);
        });
    }

    public function installStep7()
    {
        $bulk = [
            [
                'title' => 'Moderator Panel Message',
                'message' => 'The Moderator Panel uses <a href="https://ipsearch.io">IPSearch.io</a> for external IP lookup. We hope you continue to use <a href="https://ipsearch.io">IPSearch.io</a> as this supports the owner of this add-on. You can revert to the default option by unchecking "Use IPSearch.io for IP lookup" under Moderator Panel options in the admin panel.',
                'active' => 1,
                'display_order' => 1,
                'dismissible' => 1,
                'user_criteria' => '[{"rule":"is_admin","data":[]}]',
                'page_criteria' => '[{"rule":"template","data":{"name":"andrew_moderatorpanel_view"}}]',
                'notice_type' => 'block',
                'display_style' => 'primary',
                'display_duration' => 0,
                'delay_duration' => 0,
                'auto_dismiss' => 0,
            ]
        ];

        $this->db()->insertBulk('xf_notice', $bulk);

        // Rebuild the notice cache
        \XF::repository('XF:Notice')->rebuildNoticeCache();
    }

    public function installStep8()
    {
        $sm = $this->schemaManager();
        $sm->createTable('xf_andrew_mp_recent_login', function (Create $table)
        {

            $table->addColumn('login_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addColumn('ip','varchar', 255)->nullable(true)->setDefault(null);
            $table->addColumn('country', 'varchar', 255)->nullable(true)->setDefault(null);
            $table->addColumn('login_date', 'int',10)->setDefault(\XF::$time);
            $table->addPrimaryKey('login_id');

        });
    }

    public function installStep9()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_andrew_mp_user_note_category', function(Alter $table)
        {
            $table->addColumn('allowed_user_group_ids', 'blob');
            $table->addColumn('display_order', 'int')->setDefault(0);
        });
    }

    public function upgrade1020270Step1()
    {
        $sm = $this->schemaManager();
        $sm->createTable('xf_andrew_mp_user_note', function (Create $table)
        {

            $table->addColumn('note_id', 'int')->autoIncrement();
            $table->addColumn('note_user_id', 'int')->nullable(true)->setDefault(null);
            $table->addColumn('user_id', 'int')->nullable(true)->setDefault(null);
            $table->addColumn('username','varchar', 250)->nullable(true)->setDefault(null);
            $table->addColumn('create_date', 'int',10)->nullable(true)->setDefault(\XF::$time);
            $table->addColumn('message', 'mediumtext')->nullable(true)->setDefault(null);
            $table->addPrimaryKey('note_id');

        });
    }
    public function upgrade1050070Step1()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('andrew_user_note_count', 'int')->setDefault(0);
        });
    }
    
    public function upgrade1060070Step1()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_user_ignored', function(Alter $table)
        {
            $table->addColumn('andrew_forced', 'int')->setDefault(0);
            $table->addColumn('andrew_forced_user_id', 'int')->setDefault(0);
            $table->addColumn('andrew_forced_datetime', 'int')->setDefault(\XF::$time);
        });
    }
    public function upgrade1060270Step1()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_warning', function(Alter $table)
        {
            $table->addColumn('andrew_mp_thread_id', 'int')->setDefault(0);
        });
        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->dropColumns('andrew_mp_thread_id');
        });

    }
    public function upgrade1070070Step1()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_andrew_mp_user_note', function(Alter $table)
        {
            $table->addColumn('edit_user_id', 'int')->nullable(true)->setDefault(null);
            $table->addColumn('last_edit_date', 'int',10)->nullable(true)->setDefault(\XF::$time);
        });
    }
    public function upgrade1090070Step1()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('andrew_reg_country','varchar', 250)->nullable(true)->setDefault(null);
        });
    }

    public function upgrade1100000Step1()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_andrew_mp_user_note', function(Alter $table)
        {
            $table->addColumn('is_privileged', 'bool')->setDefault(0);
        });
    }

    public function upgrade1100000Step2()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('andrew_privileged_user_note_count', 'int')->setDefault(0);
        });
    }

    public function upgrade1100000Step3()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_andrew_mp_user_note', function(Alter $table)
        {
            $table->addColumn('note_category_id', 'int')->setDefault(0);
        });
    }

    public function upgrade1100000Step4()
    {
        $bulk = [
            [
                'title' => 'Moderator Panel Message',
                'message' => 'The Moderator Panel uses <a href="https://ipsearch.io">IPSearch.io</a> for external IP lookup. We hope you continue to use <a href="https://ipsearch.io">IPSearch.io</a> as this supports the owner of this add-on. You can revert to the default option by unchecking "Use IPSearch.io for IP lookup" under Moderator Panel options in the admin panel.',
                'active' => 1,
                'display_order' => 1,
                'dismissible' => 1,
                'user_criteria' => '[{"rule":"is_admin","data":[]}]',
                'page_criteria' => '[{"rule":"template","data":{"name":"andrew_moderatorpanel_view"}}]',
                'notice_type' => 'block',
                'display_style' => 'primary',
                'display_duration' => 0,
                'delay_duration' => 0,
                'auto_dismiss' => 0,
            ]
        ];

        $this->db()->insertBulk('xf_notice', $bulk);

        // Rebuild the notice cache
        \XF::repository('XF:Notice')->rebuildNoticeCache();
    }

    public function upgrade1100000Step5()
    {
        $sm = $this->schemaManager();
        $sm->createTable('xf_andrew_mp_user_note_category', function (Create $table)
        {

            $table->addColumn('note_category_id', 'int')->autoIncrement();
            $table->addColumn('title','varchar', 250)->nullable(true)->setDefault(null);
            $table->addColumn('use_count', 'int')->nullable(false)->setDefault(0);
            $table->addColumn('last_used_date', 'int')->nullable(true)->setDefault(null);

            $table->addPrimaryKey('note_category_id');

        });
    }

    public function upgrade2000070Step1()
    {
        $sm = $this->schemaManager();
        $sm->createTable('xf_andrew_mp_recent_login', function (Create $table)
        {

            $table->addColumn('login_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addColumn('ip','varchar', 255)->nullable(true)->setDefault(null);
            $table->addColumn('country', 'varchar', 255)->nullable(true)->setDefault(null);
            $table->addColumn('login_date', 'int',10)->setDefault(\XF::$time);
            $table->addPrimaryKey('login_id');

        });
    }
    public function upgrade2000070Step2()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_andrew_mp_user_note_category', function(Alter $table)
        {
            $table->addColumn('allowed_user_group_ids', 'blob');
            $table->addColumn('display_order', 'int')->setDefault(0);

        });
    }

    public function uninstallStep1()
    {
        $sm = $this->schemaManager();
        $sm->dropTable('xf_andrew_mp_user_note');
    }

    public function uninstallStep2()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->dropColumns('andrew_user_note_count');
            $table->dropColumns('andrew_reg_country');
            $table->dropColumns('andrew_privileged_user_note_count');
        });
    }
    public function uninstallStep3()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_warning', function(Alter $table)
        {
            $table->dropColumns('andrew_mp_thread_id');
        });
    }
    public function uninstallStep4()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_user_ignored', function(Alter $table)
        {
            $table->dropColumns('andrew_forced');
            $table->dropColumns('andrew_forced_user_id');
            $table->dropColumns('andrew_forced_datetime');
        });
    }

    public function uninstallStep5()
    {
        $sm = $this->schemaManager();
        $sm->dropTable('xf_andrew_mp_user_note_category');
    }

    public function uninstallStep6()
    {
        $sm = $this->schemaManager();
        $sm->dropTable('xf_andrew_mp_recent_login');
    }

}