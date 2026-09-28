<?php

namespace XFDev\LuckyAwards;

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

	public function installStep1()
    {
        $sm = $this->schemaManager();

        if(!$sm->tableExists('xfdev_lucky_awards'))
        {
            $sm->createTable('xfdev_lucky_awards',function (Create $table){
                $table->addColumn('lucky_award_id','int')->autoIncrement();
                $table->addColumn('lucky_award_title','varchar',255)->setDefault('');
                $table->addColumn('lucky_award_active','int')->setDefault(0);
                $table->addColumn('award_id','int')->setDefault(0);
                $table->addColumn('lucky_award_chances','int')->setDefault(0);
                $table->addColumn('lucky_award_dependent','longtext');
                $table->addColumn('lucky_award_reason', 'text');

                $table->addPrimaryKey('lucky_award_id');
                $table->addKey('award_id','award_id');
            });


        }

        if(!$sm->tableExists('xfdev_users_lucky_award'))
        {
            $sm->createTable('xfdev_users_lucky_award',function(Create $table){
                $table->addColumn('user_lucky_award_id','int')->autoIncrement();
                $table->addColumn('lucky_award_id','int')->setDefault(0);
                $table->addColumn('post_id','int')->setDefault(0)->nullable();
                $table->addColumn('user_id','int')->setDefault(0);
                $table->addColumn('date_received','int')->setDefault(null)->nullable();

                $table->addPrimaryKey('user_lucky_award_id');
                $table->addUniqueKey(['lucky_award_id','user_id'],'lucky_award_user');
                $table->addKey('user_id','user_id');
                $table->addKey('post_id','post_id');
            });
        }
    }


    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        $sm->dropTable('xfdev_lucky_awards');
        $sm->dropTable('xfdev_users_lucky_award');
    }
}