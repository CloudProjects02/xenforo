<?php

namespace EAEAddons\ThreadCount;

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

		$sm->alterTable('xf_user', function(Alter $table)
		{
			$table->addColumn('eaetc_thread_count', 'int')->unsigned()->setDefault(0);
			$table->addKey('eaetc_thread_count', 'thread_count');
		});
	}

	public function upgrade1010170Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user', function(Alter $table)
		{
			$table->addKey('eaetc_thread_count', 'thread_count');
		});
	}

	public function uninstallStep1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user', function(Alter $table)
		{
			$table->dropColumns('eaetc_thread_count');
		});
	}
}