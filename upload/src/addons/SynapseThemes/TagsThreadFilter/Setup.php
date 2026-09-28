<?php

namespace SynapseThemes\TagsThreadFilter;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUninstallTrait;
	use StepRunnerUpgradeTrait;

	public function installStep1()
	{
		// Basic setup steps, e.g., creating tables or adding columns
	}

	public function uninstallStep1()
	{
		// Clean up, e.g., dropping tables or removing columns
	}

	// Add more install/upgrade/uninstall steps as needed
}