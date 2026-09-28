<?php

namespace SynapseThemes\FeaturedUserUpgrade;

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

	public function install(array $stepParams = [])
	{
		$this->schemaManager()->alterTable('xf_user_upgrade', function(Alter $table)
		{
			$table->addColumn('is_featured', 'tinyint', 1)->setDefault(0);
		});
	}

	public function upgrade(array $stepParams = [])
	{
		// TODO: Implement upgrade() method.
	}

	public function uninstall(array $stepParams = [])
	{
		$this->schemaManager()->alterTable('xf_user_upgrade', function(Alter $table)
		{
			$table->dropColumns(['is_featured']);
		});
	}
}