<?php

namespace ChunkedUploads;

use XF\AddOn\AbstractSetup;

class Setup extends AbstractSetup
{
    public function install(array $stepParams = [])
    {
        $internalDataDir = \XF\Util\File::canonicalizePath($this->app->config('internalDataPath'));

        \XF\Util\File::createDirectory($internalDataDir . '/chunked_uploads/temp', true);
    }

    public function upgrade(array $stepParams = [])
    {
    }

    public function uninstall(array $stepParams = [])
    {
        $internalDataDir = \XF\Util\File::canonicalizePath($this->app->config('internalDataPath'));

        \XF\Util\File::deleteDirectory($internalDataDir . '/chunked_uploads');
    }
}