<?php


namespace AddonsLab\Core\Service;


class DirectorySynchronizer
{
    public function syncDirectories($source, $target)
    {
        // get list of files in the target directory
        $targetDirectoryFiles = $this->_readDirectory($target);
        $sourceDirectoryFiles = $this->_readDirectory($source);

        foreach ($targetDirectoryFiles as $targetDirectoryFile)
        {
            $targetFullPath = $target . DIRECTORY_SEPARATOR . $targetDirectoryFile;

            if (!in_array($targetDirectoryFile, $sourceDirectoryFiles, true))
            {
                if (is_dir($targetFullPath))
                {
                    // directory exists in the target and does not exist in the source
                    $this->deleteDirectory($targetFullPath);
                }
                else
                {
                    // Delete the file
                    unlink($targetFullPath);
                }
            }
            else if (is_dir($targetFullPath) && is_dir($source . DIRECTORY_SEPARATOR . $targetDirectoryFile))
            {
                // We have sub-directories with the same name, synchronize them recursively
                $this->syncDirectories($source . DIRECTORY_SEPARATOR . $targetDirectoryFile, $target . DIRECTORY_SEPARATOR . $targetDirectoryFile);
            }
        }
    }

    public function deleteDirectory($directory)
    {
        if(!is_dir($directory))
        {
            return;
        }
        
        $files = $this->_readDirectory($directory);

        foreach ($files as $file)
        {
            $fullPath = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_link($fullPath) || is_file($fullPath))
            {
                unlink($fullPath);
            }
            else if (is_dir($fullPath))
            {
                $this->deleteDirectory($fullPath);
            }
        }

        rmdir($directory);
    }

    protected function _readDirectory($directory)
    {
        $files = scandir($directory);
        $files = array_filter($files, function ($file)
        {
            return !in_array($file, ['.', '..']);
        });
        return $files;
    }
}