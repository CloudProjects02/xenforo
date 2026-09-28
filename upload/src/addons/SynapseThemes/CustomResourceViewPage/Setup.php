<?php

namespace SynapseThemes\CustomResourceViewPage;

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
		// Add any installation steps here if needed
	}

	public function uninstallStep1()
	{
		// Add any uninstallation steps here if needed
	}
}