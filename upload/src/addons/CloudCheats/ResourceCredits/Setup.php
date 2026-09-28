<?php

namespace CloudCheats\ResourceCredits;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUninstallTrait;
	use StepRunnerUpgradeTrait;

	public function installStep1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_rm_resource', function ($table)
		{
			$table->addColumn('cc_credits_price', 'decimal', '10,2')->nullable()->setDefault(null);
		});

		$sm->createTable('cc_resource_purchase', function ($table)
		{
			$table->addColumn('resource_id', 'int')->unsigned();
			$table->addColumn('user_id', 'int')->unsigned();
			$table->addColumn('purchase_date', 'int')->unsigned();
			$table->addColumn('credits_paid', 'decimal', '10,2');
			$table->addPrimaryKey(['resource_id', 'user_id']);
		});
	}

	public function uninstallStep1(): void
	{
		$sm = $this->schemaManager();
		$sm->alterTable('xf_rm_resource', function ($table)
		{
			$table->dropColumns('cc_credits_price');
		});
		$sm->dropTable('cc_resource_purchase');
	}
}
