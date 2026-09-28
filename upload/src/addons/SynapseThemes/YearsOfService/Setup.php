<?php

namespace SynapseThemes\YearsOfService;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function installStep1()
	{
		// No database changes needed for this addon
		// All functionality is template-based
	}

	public function uninstallStep1()
	{
		// Clean up any cached data if needed
	}

	public function upgrade1000010Step1()
	{
		// Future upgrade steps
	}
}