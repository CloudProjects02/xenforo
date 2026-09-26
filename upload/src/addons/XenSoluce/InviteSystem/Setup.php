<?php

namespace XenSoluce\InviteSystem;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Db\SchemaManager;

/**
 * Class Setup
 * @package XenSoluce\InviteSystem
 */
class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

    /**
     * @param array $stepParams
     */
    public function installStep1(array $stepParams = [])
    {
        $sm = $this->schemaManager();

        $sm->createTable('xf_xs_is_ban', function (Create $table)
        {
            $table->addColumn('user_id', 'int');
            $table->addColumn('ban_user_id', 'int');
            $table->addColumn('ban_date', 'int')->setDefault(0);
            $table->addColumn('end_date', 'int')->setDefault(0);
            $table->addColumn('ban_reason', 'varchar', 255);
            $table->addPrimaryKey('user_id');
        });
        $sm->createTable('xf_xs_is_code_invitation', function (Create $table)
        {
            $table->addColumn('code_id', 'int')->autoIncrement();
            $table->addColumn('code', 'varchar',32);
            $table->addColumn('user_id', 'int');
            $table->addColumn('token_id', 'int');
            $table->addColumn('token', 'varchar',32);
            $table->addColumn('registered_user_id', 'int');
            $table->addColumn('invitation_date', 'int');
            $table->addColumn('type_code', 'int')->setDefault(1);
            $table->addPrimaryKey('code_id');
        });
        $sm->createTable('xf_xs_is_personalized_invitation_code', function (Create $table)
        {
            $table->addColumn('ic_personalize_id', 'int')->autoIncrement();
            $table->addColumn('title', 'varchar',50);
            $table->addColumn('code', 'varchar',32);
            $table->addColumn('limit_use', 'int', 11)->unsigned('');
            $table->addColumn('limit_time', 'int', 11)->unsigned('');
            $table->addColumn('registered_user_id', 'varbinary',255);
            $table->addColumn('invitation_date', 'int');
            $table->addColumn('enable', 'tinyint', 3);
            $table->addPrimaryKey('ic_personalize_id');
        });
        $sm->createTable('xf_xs_is_token', function (Create $table)
        {
            $table->addColumn('token_id', 'int')->autoIncrement();
            $table->addColumn('title', 'varchar',100);
            $table->addColumn('token', 'varchar',32);
            $table->addColumn('type_token', 'int');
            $table->addColumn('user', 'varbinary',255);
            $table->addColumn('number_use', 'int');
            $table->addPrimaryKey('token_id');
        });
        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('xs_is_invite_count', 'int');
        });
    }
    /**Version : 2.1.0*/
    public function upgrade2010000Step1()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_xs_is_token', function(Alter $table)
        {
            $table->renameColumn('user_group_id', 'user');
            $table->addColumn('type_token', 'int');
            $table->addColumn('number_use', 'int');
        });
        $sm->alterTable('xf_xs_is_code_invitation', function(Alter $table)
        {
            $table->addColumn('token', 'varchar',32);
        });
        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('xs_is_invite_count', 'int')->setDefault(1);
        });
    }
    /**Version : 2.1.3*/
    public function upgrade2010300Step1()
    {
        $sm = $this->schemaManager();
        $sm->alterTable('xf_xs_is_code_invitation', function(Alter $table)
        {
            $table->addColumn('type_code', 'int')->setDefault(1);
        });

    }
    /**Version : 2.1.5*/
    public function upgrade2010500Step1()
    {
        $sm = $this->schemaManager();

        $sm->createTable('xf_xs_is_personalized_invitation_code', function (Create $table)
        {
            $table->addColumn('ic_personalize_id', 'int')->autoIncrement();
            $table->addColumn('title', 'varchar',50);
            $table->addColumn('code', 'varchar',32);
            $table->addColumn('limit_use', 'int', 11)->unsigned('');
            $table->addColumn('limit_time', 'int', 11)->unsigned('');
            $table->addColumn('registered_user_id', 'varbinary',255);
            $table->addColumn('invitation_date', 'int');
            $table->addColumn('enable', 'tinyint', 3);
            $table->addPrimaryKey('ic_personalize_id');
        });
    }
    /**Version : 2.1.5 Fix 1*/
    public function upgrade2010510Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_xs_is_personalized_invitation_code', function(Alter $table)
        {
            $table->changeColumn('limit_use', 'int', 11)->unsigned('');
            $table->changeColumn('limit_time', 'int', 11)->unsigned('');
        });
    }
    /**Version : 2.1.5 Fix 2*/
    public function upgrade2010520Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->changeColumn('xs_is_invite_count')->setDefault(0);
        });
    }

    /**
     * @param array $stepParams
     */
    public function uninstallStep1(array $stepParams = [])
    {
        $sm = $this->schemaManager();
        $sm->dropTable('xf_xs_is_ban');
        $sm->dropTable('xf_xs_is_code_invitation');
        $sm->dropTable('xf_xs_is_personalized_invitation_code');
        $sm->dropTable('xf_xs_is_token');
        $sm->alterTable('xf_user', function(Alter $table)
        {
            $table->dropColumns('xs_is_invite_count');
        });
    }
}