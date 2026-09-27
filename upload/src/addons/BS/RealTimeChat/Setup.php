<?php

namespace BS\RealTimeChat;

use BS\RealTimeChat\Setup\SetupDefaults;
use BS\RealTimeChat\Setup\SetupTables;
use BS\RealTimeChat\Setup\SetupWidgets;
use BS\RealTimeChat\Setup\Upgrade1000032;
use BS\RealTimeChat\Setup\Upgrade1000070;
use BS\RealTimeChat\Setup\Upgrade1000270;
use BS\RealTimeChat\Setup\Upgrade1010070;
use BS\RealTimeChat\Setup\Upgrade2000070;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    use SetupTables;
    use SetupWidgets;
    use SetupDefaults;

    // Version 1.0.0 Beta 2
    use Upgrade1000032;

    // Version 1.0.0
    use Upgrade1000070;

    // Version 1.0.2
    use Upgrade1000270;

    // Version 1.1.0
    use Upgrade1010070;

    // Version 2.0.0
    use Upgrade2000070;
}
