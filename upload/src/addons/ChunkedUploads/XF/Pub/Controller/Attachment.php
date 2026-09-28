<?php

namespace ChunkedUploads\XF\Pub\Controller;

class Attachment extends XFCP_Attachment
{
    public function actionUpload()
    {
        if (isset($_FILES['upload']))
        {
            $internalDataDir = \XF\Util\File::canonicalizePath($this->app->config('internalDataPath'));
            $chunksDir = $internalDataDir . '/chunked_uploads/';
            $tempDir = $chunksDir . 'temp';

            if (1 == mt_rand(1, 100)) {
                try {
                    \Flow\Uploader::pruneChunks($tempDir);
                    \Flow\Uploader::pruneChunks($chunksDir, 86400);
                } catch (\Flow\FileOpenException $e) {
                }
            }

            $config = new \Flow\Config();
            $config->setTempDir($tempDir);

            $request = new \Flow\Request(null, $_FILES['upload']);

            $file = new \Flow\File($config, $request);

            if ($file->validateChunk())
            {
                $uploadFileName = uniqid()."_".$request->getFileName();
                $uploadPath = $chunksDir.$uploadFileName;

                if (! \Flow\Basic::save($uploadPath, $config, $request)) {
                    // Headers has been already sent, stop executing
                    exit;
                }

                $_FILES['upload']['name'] = $request->getFileName();
                $_FILES['upload']['tmp_name'] = $uploadPath;
                $_FILES['upload']['size'] = $request->getTotalSize();

                $this->request = new \XF\Http\Request(new \XF\InputFilterer());
            }
        }

        return parent::actionUpload();
    }
}