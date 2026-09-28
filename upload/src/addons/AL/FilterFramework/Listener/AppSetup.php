<?php
/** 
* @package [AL] Filter Framework
* @author AddonsLab
* @license https://addonslab.com/
* @link https://addonslab.com/
* @version 1.4.3
This software is furnished under a license and may be used and copied
only  in  accordance  with  the  terms  of such  license and with the
inclusion of the above copyright notice.  This software  or any other
copies thereof may not be provided or otherwise made available to any
other person.  No title to and  ownership of the  software is  hereby
transferred.                                                         
                                                                     
You may not reverse  engineer, decompile, defeat  license  encryption
mechanisms, or  disassemble this software product or software product
license.  AddonsLab may terminate this license if you don't comply with
any of these terms and conditions.  In such event,  licensee  agrees 
to return licensor  or destroy  all copies of software  upon termination 
of the license.
*/


namespace AL\FilterFramework\Listener;

use AL\FilterFramework\Extension;
use XF\App;
use XF\Container;
use XF\Db\Exception;

class AppSetup
{
    public static function listen(App $app)
    {
        $container = $app->container();

        $original_extension = $container['extension'];

        if ($original_extension instanceof Extension)
        {
            // Extension has been replaced with the proxy already
            return;
        }

        $container['extension'] = function (Container $c) use ($original_extension) {
            $config = $c['config'];
            if (!$config['enableListeners'])
            {
                // disable
                return new \XF\Extension();
            }

            try
            {
                $listeners = $c['extension.listeners'];
                $classExtensions = $c['extension.classExtensions'];
            }
            catch (Exception $e)
            {
                $listeners = [];
                $classExtensions = [];
            }

            $extension = new Extension($listeners, $classExtensions);
            $extension->setOriginalExtension($original_extension);

            return $extension;
        };
    }
}
