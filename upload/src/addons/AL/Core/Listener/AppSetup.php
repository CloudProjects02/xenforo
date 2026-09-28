<?php

namespace AL\Core\Listener;

use AddonsLab\Core\App;
use AddonsLab\Core\Service\Logger;
use AddonsLab\Core\XF2;
use AL\Core\Compat;
use AL\Core\Extension;
use XF\Container;

class AppSetup
{
    public static function listen(\XF\App $app)
    {
        require_once __DIR__ . '/../../../AddonsLab/Core/vendor/autoload.php';
    }
}

