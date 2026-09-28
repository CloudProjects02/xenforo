<?php

namespace Siropu\EasyUserBan;

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
          $this->schemaManager()->alterTable('xf_user', function(Alter $table)
		{
               $table->addColumn('siropu_easy_user_ban_count', 'int')->setDefault(0);
               $table->addColumn('siropu_easy_user_ban_forum', 'blob');
               $table->addColumn('siropu_easy_user_ban_thread', 'blob');
		});
     }
	public function installStep2()
	{
		$this->schemaManager()->createTable('xf_siropu_easy_user_ban_log', function(Create $table)
		{
			$table->addColumn('log_id', 'int')->autoIncrement();
               $table->addColumn('ban_type', 'enum')->values(['board', 'forum', 'thread'])->setDefault('board');
               $table->addColumn('item_id', 'int')->setDefault(0);
			$table->addColumn('action', 'enum')->values(['ban', 'unban'])->setDefault('ban');
			$table->addColumn('action_reason', 'varchar', 255);
			$table->addColumn('action_user_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('ips', 'text');
			$table->addColumn('date', 'int');
			$table->addColumn('end_date', 'int')->setDefault(0);
               $table->addKey('ban_type');
               $table->addKey('item_id');
			$table->addKey('action_user_id');
			$table->addKey('user_id');
			$table->addKey('date');
		});

          $this->addNodeThreadBanTables();
	}
	public function installStep3()
	{
		$this->createWidget('siropu_eub_recent_bans', 'siropu_eub_recent_bans', [
			'positions' => [
				'siropu_easy_user_ban_list_sidebar' => 10
			]
		]);

		$this->createWidget('siropu_eub_expiring_bans', 'siropu_eub_expiring_bans', [
			'positions' => [
				'siropu_easy_user_ban_list_sidebar' => 20
			]
		]);
	}
     public function upgrade2000370Step1()
     {
          $this->schemaManager()->alterTable('xf_user', function(Alter $table)
		{
               $table->addColumn('siropu_easy_user_ban_count', 'int')->setDefault(0);
		});
     }
     public function upgrade2000370Step2()
     {
          $results = $this->db()->fetchAll('
               SELECT user_id, COUNT(user_id) as ban_count
               FROM xf_siropu_easy_user_ban_log
               WHERE action = "ban"
               GROUP by user_id
          ');

          foreach ($results as $result)
          {
               $this->db()->update('xf_user', ['siropu_easy_user_ban_count' => $result['ban_count']], 'user_id = ?', $result['user_id']);
          }
     }
     public function upgrade2010070Step1()
     {
          $this->schemaManager()->alterTable('xf_siropu_easy_user_ban_log', function(Alter $table)
		{
			$table->addColumn('ban_type', 'enum')->values(['board', 'forum', 'thread'])->setDefault('board');
               $table->addColumn('item_id', 'int')->setDefault(0);
               $table->addKey('ban_type');
               $table->addKey('item_id');
		});
     }
     public function upgrade2010070Step2()
     {
          $this->addNodeThreadBanTables();
     }
     public function upgrade2010070Step3()
     {
          $this->schemaManager()->alterTable('xf_user', function(Alter $table)
		{
               $table->addColumn('siropu_easy_user_ban_forum', 'blob');
               $table->addColumn('siropu_easy_user_ban_thread', 'blob');
		});
     }
	public function upgrade2010270Step1()
     {
          $this->schemaManager()->alterTable('xf_siropu_easy_user_ban_log', function(Alter $table)
		{
			$table->changeColumn('ban_type', 'enum')->values(['board', 'forum', 'thread'])->setDefault('board');
		});
     }
     public function uninstallStep1()
	{
          $this->schemaManager()->alterTable('xf_user', function(Alter $table)
		{
			$table->dropColumns(['siropu_easy_user_ban_count', 'siropu_easy_user_ban_forum', 'siropu_easy_user_ban_thread']);
		});
	}
     public function uninstallStep2()
	{
		$this->schemaManager()->dropTable('xf_siropu_easy_user_ban_log');
          $this->schemaManager()->dropTable('xf_siropu_easy_user_ban_forum');
          $this->schemaManager()->dropTable('xf_siropu_easy_user_ban_thread');
	}
     public function uninstallStep3()
     {
          $this->deleteWidget('siropu_eub_recent_bans');
          $this->deleteWidget('siropu_eub_expiring_bans');
     }
     protected function addNodeThreadBanTables()
     {
          $sm = $this->schemaManager();

          $sm->createTable('xf_siropu_easy_user_ban_forum', function(Create $table)
		{
               $table->addColumn('node_id', 'int');
			$table->addColumn('user_id', 'int');
               $table->addColumn('ban_user_id', 'int');
               $table->addColumn('user_reason', 'varchar', 255);
			$table->addColumn('ban_date', 'int');
			$table->addColumn('end_date', 'int')->setDefault(0);
               $table->addPrimaryKey(['node_id', 'user_id']);
			$table->addKey('ban_user_id');
			$table->addKey('ban_date');
		});

          $sm->createTable('xf_siropu_easy_user_ban_thread', function(Create $table)
		{
               $table->addColumn('thread_id', 'int');
			$table->addColumn('user_id', 'int');
               $table->addColumn('ban_user_id', 'int');
               $table->addColumn('user_reason', 'varchar', 255);
			$table->addColumn('ban_date', 'int');
			$table->addColumn('end_date', 'int')->setDefault(0);
               $table->addPrimaryKey(['thread_id', 'user_id']);
			$table->addKey('ban_user_id');
			$table->addKey('ban_date');
		});
     }
}
 		  	   				  		 	 	  	  	  		 	  	   			   	  		 			 		 				  		 				   		 				 		  	 	 	   		  			   	 		 	  		 		  	 	 	  		  	 
