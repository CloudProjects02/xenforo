<?php

namespace XenDev\StaffManager;

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

        if (!$sm->tableExists('xf_xendev_staff_block'))
        {
            $sm->createTable('xf_xendev_staff_block', function (Create $table)
            {
                $table->addColumn('block_id', 'int')->autoIncrement();
                $table->addColumn('title', 'varchar', 100);
                $table->addColumn('source_type', 'varchar', 25)->setDefault('user_group');
                $table->addColumn('user_group_id', 'int')->setDefault(0);
                $table->addColumn('node_id', 'int')->setDefault(0);
                $table->addColumn('group_match_type', 'varchar', 20)->setDefault('both');
                $table->addColumn('display_order', 'int')->setDefault(0);
                $table->addColumn('icon_class', 'varchar', 50)->setDefault('fa-users');
                $table->addColumn('active', 'tinyint')->setDefault(1);

                $table->addPrimaryKey('block_id');
            });
        }
    }

    public function upgrade1000011Step1()
    {
        $sm = $this->schemaManager();

        if ($sm->tableExists('xf_xendev_staff_block'))
        {
            $sm->alterTable('xf_xendev_staff_block', function (Alter $table)
            {
                if (!$table->getColumnDefinition('node_id'))
                {
                    $table->addColumn('node_id', 'int')->setDefault(0)->after('user_group_id');
                }
            });
        }
    }

    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        if ($sm->tableExists('xf_xendev_staff_block'))
        {
            $sm->dropTable('xf_xendev_staff_block');
        }
    }
}
 		  					 	  		   	  			   	  			  	    	 				     		 			 		 		 	  			     	  	 		 			  	   	   	 		  	    		  			      	      		  
