<?php

namespace ChunkedUploads;

use XF\Container;

class Listener
{
    public static function app_setup(\XF\App $app)
    {
        $app->container()->set('realUploadMaxFilesize', $app->container('uploadMaxFilesize'));

        $app->container()->set('uploadMaxFilesize', function(Container $c)
        {
            if ($c['options']['chunkedUploadsIncreaseMaxFileSize'])
            {
                return 128 * 1024 * 1024 * 1024;
            } else {
                return \XF\Util\Php::getUploadMaxFilesize();
            }
        });
    }
}