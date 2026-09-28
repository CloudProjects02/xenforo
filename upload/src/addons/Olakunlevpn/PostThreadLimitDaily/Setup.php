<?php

namespace Olakunlevpn\PostThreadLimitDaily;

use XF\AddOn\AbstractSetup;

class Setup extends AbstractSetup
{
    public function install(array $stepParams = [])
    {
        // No custom tables needed
    }
    
    public function upgrade(array $stepParams = [])
    {
        // No upgrade steps needed
    }
    
    public function uninstall(array $stepParams = [])
    {

    }
}
