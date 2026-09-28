<?php

namespace SynapseThemes\XFRMIndexSliders\XFRM\Service\ResourceItem;

class Icon extends XFCP_Icon
{
    protected function getSizeMap()
    {
        return [
            's' => 256,  // Small icon size
            'm' => 512,  // Medium icon size
            'l' => 768,  // Large icon size
            'h' => 1024  // High/extra large icon size
        ];
    }

    public function updateIcon()
    {
        if (!$this->fileName)
        {
            throw new \LogicException("No source file for icon set");
        }

        $sizeMap = $this->getSizeMap();
        
        if (!$sizeMap)
        {
            throw new \Exception('Resource icon size map not found');
        }

        $outputFiles = [];
        $imageManager = $this->app->imageManager();
        
        // Load the original image once
        $originalImage = $imageManager->imageFromFile($this->fileName);
        if (!$originalImage)
        {
            return false;
        }

        $isOptimized = $originalImage->getType() === IMAGETYPE_WEBP;
        $originalWidth = $originalImage->getWidth();
        $originalHeight = $originalImage->getHeight();
        
        foreach ($sizeMap as $sizeCode => $targetSize)
        {
            // Create a new image for this size
            $resizedImage = $imageManager->imageFromFile($this->fileName);
            if (!$resizedImage)
            {
                continue;
            }
            
            // Resize and crop to maintain square dimensions
            $resizedImage->resizeAndCrop($targetSize);
            
            // Ensure temp directory exists and is writable
            $tempDir = \XF\Util\File::getTempDir();
            if (!is_dir($tempDir) || !is_writable($tempDir))
            {
                throw new \RuntimeException("Temp directory is not writable: {$tempDir}");
            }
            
            // Save to temporary file
            $tempFile = \XF\Util\File::getTempFile();
            if (!$tempFile)
            {
                throw new \RuntimeException("Failed to create temporary file");
            }
            
            if (!$resizedImage->save($tempFile))
            {
                throw new \RuntimeException("Failed to save image to temporary file");
            }
            
            // Get abstracted path for this size
            $abstractedPath = $this->resource->getAbstractedIconPathForSize($sizeCode);
            
            // Copy to final location
            if (!\XF\Util\File::copyFileToAbstractedPath($tempFile, $abstractedPath))
            {
                throw new \RuntimeException("Failed to copy file to abstracted path: {$abstractedPath}");
            }
            
            $outputFiles[] = $abstractedPath;
        }

        if (!$outputFiles)
        {
            throw new \RuntimeException("No output files were generated");
        }

        $this->resource->bulkSet([
            'icon_date' => \XF::$time,
            'icon_optimized' => $isOptimized,
        ]);
        $this->resource->save();

        if ($this->logIp)
        {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog('update', $ip);
        }

        return true;
    }

    public function deleteIconFiles()
    {
        $sizeMap = $this->getSizeMap();
        
        if (!$sizeMap)
        {
            return;
        }

        foreach ($sizeMap as $sizeCode => $size)
        {
            $abstractedPath = $this->resource->getAbstractedIconPathForSize($sizeCode);
            \XF\Util\File::deleteFromAbstractedPath($abstractedPath);
        }
    }
} 