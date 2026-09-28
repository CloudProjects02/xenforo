<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;

/**
 * Class Icon
 * @package DBTech\Shop\Service\Item
 */
class Icon extends \XF\Service\AbstractService
{
    /** @var \DBTech\Shop\Entity\Item */
    protected $item;

    /** @var bool */
    protected $logIp = true;

    /** @var */
    protected $fileName;

    /** @var */
    protected $width;

    /** @var */
    protected $height;

    /** @var */
    protected $type;

    /** @var null */
    protected $error;

    /** @var array */
    protected $allowedTypes = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG];

    /** @var bool */
    protected $isAnimated = false;

    /**
     * Icon constructor.
     * @param \XF\App $app
     * @param Item $item
     */
    public function __construct(\XF\App $app, Item $item)
    {
        parent::__construct($app);
        $this->item = $item;
    }

    /**
     * @return Item
     */
    public function getItem(): Item
    {
        return $this->item;
    }

    /**
     * @param bool $logIp
     *
     * @return $this
     */
    public function logIp(bool $logIp): Icon
    {
        $this->logIp = $logIp;

        return $this;
    }

    /**
     * @return null
     */
    public function getError()
    {
        return $this->error;
    }
    
    /**
     * @param string $fileName
     *
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function setImage(string $fileName): bool
    {
        if (!$this->validateImageAsIcon($fileName, $error))
        {
            $this->error = $error;
            $this->fileName = null;
            return false;
        }

        $this->fileName = $fileName;
        return true;
    }
    
    /**
     * @param \XF\Http\Upload $upload
     *
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function setImageFromUpload(\XF\Http\Upload $upload): bool
    {
        $upload->requireImage();

        if (!$upload->isValid($errors))
        {
            $this->error = reset($errors);
            return false;
        }

        return $this->setImage($upload->getTempFile());
    }

    /**
     * Check if PNG file is animated (APNG)
     *
     * @param string $fileName
     * @return bool
     */
    protected function isAnimatedPng(string $fileName): bool
    {
        $handle = fopen($fileName, 'rb');
        if (!$handle) {
            return false;
        }

        // Read PNG signature
        $signature = fread($handle, 8);
        if ($signature !== "\x89PNG\r\n\x1a\n") {
            fclose($handle);
            return false;
        }

        // Look for acTL chunk (Animation Control)
        while (!feof($handle)) {
            $lengthData = fread($handle, 4);
            if (strlen($lengthData) < 4) {
                break;
            }
            
            $length = unpack('N', $lengthData)[1] ?? 0;
            $type = fread($handle, 4);
            
            if (strlen($type) < 4) {
                break;
            }
            
            if ($type === 'acTL') {
                fclose($handle);
                return true; // Found animation control chunk
            }
            
            if ($type === 'IDAT') {
                // If we hit image data without finding acTL, it's not animated
                fclose($handle);
                return false;
            }
            
            // Skip chunk data and CRC
            fseek($handle, $length + 4, SEEK_CUR);
        }
        
        fclose($handle);
        return false;
    }

    /**
     * Check if GIF file is animated
     *
     * @param string $fileName
     * @return bool
     */
    protected function isAnimatedGif(string $fileName): bool
    {
        $handle = fopen($fileName, 'rb');
        if (!$handle) {
            return false;
        }

        // Read GIF header
        $header = fread($handle, 6);
        if (!in_array($header, ['GIF87a', 'GIF89a'])) {
            fclose($handle);
            return false;
        }

        // Skip logical screen descriptor
        fseek($handle, 4, SEEK_CUR);
        
        $packed = fread($handle, 1);
        $globalColorTableFlag = (ord($packed) & 0x80) >> 7;
        $globalColorTableSize = ord($packed) & 0x07;

        // Skip background color index and pixel aspect ratio
        fseek($handle, 2, SEEK_CUR);

        // Skip global color table if present
        if ($globalColorTableFlag) {
            $globalColorTableEntries = pow(2, $globalColorTableSize + 1);
            fseek($handle, $globalColorTableEntries * 3, SEEK_CUR);
        }

        $imageCount = 0;
        
        // Look for image separators
        while (!feof($handle)) {
            $byte = fread($handle, 1);
            if ($byte === false) break;
            
            $byte = ord($byte);
            
            if ($byte === 0x21) { // Extension
                $label = fread($handle, 1);
                if ($label === false) break;
                
                // Skip extension data
                do {
                    $blockSize = fread($handle, 1);
                    if ($blockSize === false) break 2;
                    $blockSize = ord($blockSize);
                    if ($blockSize > 0) {
                        fseek($handle, $blockSize, SEEK_CUR);
                    }
                } while ($blockSize > 0);
                
            } elseif ($byte === 0x2C) { // Image separator
                $imageCount++;
                if ($imageCount > 1) {
                    fclose($handle);
                    return true; // More than one image = animated
                }
                
                // Skip image descriptor
                fseek($handle, 8, SEEK_CUR);
                
                $packed = fread($handle, 1);
                if ($packed === false) break;
                
                $localColorTableFlag = (ord($packed) & 0x80) >> 7;
                $localColorTableSize = ord($packed) & 0x07;
                
                // Skip local color table if present
                if ($localColorTableFlag) {
                    $localColorTableEntries = pow(2, $localColorTableSize + 1);
                    fseek($handle, $localColorTableEntries * 3, SEEK_CUR);
                }
                
                // Skip LZW minimum code size
                fseek($handle, 1, SEEK_CUR);
                
                // Skip image data
                do {
                    $blockSize = fread($handle, 1);
                    if ($blockSize === false) break 2;
                    $blockSize = ord($blockSize);
                    if ($blockSize > 0) {
                        fseek($handle, $blockSize, SEEK_CUR);
                    }
                } while ($blockSize > 0);
                
            } elseif ($byte === 0x3B) { // Trailer
                break;
            }
        }
        
        fclose($handle);
        return false;
    }
    
    /**
     * @param string $fileName
     * @param null $error
     *
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function validateImageAsIcon(string $fileName, &$error = null): bool
    {
        $error = null;

        if (!file_exists($fileName))
        {
            throw new \InvalidArgumentException("Invalid file '$fileName' passed to icon service");
        }
        if (!is_readable($fileName))
        {
            throw new \InvalidArgumentException("'$fileName' passed to icon service is not readable");
        }

        $imageInfo = filesize($fileName) ? getimagesize($fileName) : false;
        if (!$imageInfo)
        {
            $error = \XF::phrase('provided_file_is_not_valid_image');
            return false;
        }

        $type = $imageInfo[2];
        if (!in_array($type, $this->allowedTypes))
        {
            $error = \XF::phrase('provided_file_is_not_valid_image');
            return false;
        }
        
        [$width, $height] = $imageInfo;

        // Check for animated files
        $this->isAnimated = false;
        if ($type === IMAGETYPE_PNG && $this->isAnimatedPng($fileName)) {
            $this->isAnimated = true;
            $this->type = 'animated_png';
        } elseif ($type === IMAGETYPE_GIF && $this->isAnimatedGif($fileName)) {
            $this->isAnimated = true;
            $this->type = 'animated_gif';
        } else {
            $this->type = $type;
        }

        if (!$this->app->imageManager()->canResize($width, $height))
        {
            $error = \XF::phrase('uploaded_image_is_too_big');
            return false;
        }

        $this->width = $width;
        $this->height = $height;

        return true;
    }
    
    /**
     * @return bool
     * @throws \RuntimeException
     * @throws \LogicException
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function updateIcon(): bool
    {
        if (!$this->fileName)
        {
            throw new \LogicException('No source file for icon set');
        }
        
        $imageManager = $this->app->imageManager();
        
        if ($this->app->options()->offsetExists('dbtechShopItemIconMaxDimensions'))
        {
            $targetWidth = $this->app->options()->dbtechShopItemIconMaxDimensions['width'] ?:
                $this->app->container('avatarSizeMap')['l'];
            
            $targetHeight = $this->app->options()->dbtechShopItemIconMaxDimensions['height'] ?:
                null;
        }
        else
        {
            $targetWidth = $this->app->container('avatarSizeMap')['l'];
            $targetHeight = $this->app->container('avatarSizeMap')['l'];
        }
        
        $outputFile = null;
        
        // Skip resizing for animated files to preserve animation
        if ($this->isAnimated || $this->type === 'animated_png' || $this->type === 'animated_gif') {
            $outputFile = $this->fileName;
        }
        // Resize static images normally
        elseif ($this->width != $targetWidth || ($targetHeight !== null && $this->height != $targetHeight))
        {
            $image = $imageManager->imageFromFile($this->fileName);
            if (!$image)
            {
                return false;
            }

            if ($targetHeight === null)
            {
                $image->resizeWidth($targetWidth);
            }
            else
            {
                $image->resizeAndCrop($targetWidth, $targetHeight);
            }
            
            $newTempFile = \XF\Util\File::getTempFile();
            if ($newTempFile && $image->save($newTempFile))
            {
                $outputFile = $newTempFile;
            }
        }
        else
        {
            $outputFile = $this->fileName;
        }
        
        if (!$outputFile)
        {
            throw new \RuntimeException('Failed to save image to temporary file; check internal_data/data permissions');
        }

        $dataFile = $this->item->getAbstractedIconPath();
        \XF\Util\File::copyFileToAbstractedPath($outputFile, $dataFile);
        
        if (!$this->logIp)
        {
            $this->item->setOption('log_moderator', false);
            $this->item->setOption('is_automated', true);
        }

        $this->item->icon_date = \XF::$time;
        $this->item->save();

        if ($this->logIp)
        {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog('update', $ip);
        }

        return true;
    }
    
    /**
     * @return bool
     * @throws \LogicException
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function deleteIcon(): bool
    {
        $this->deleteIconFiles();

        $this->item->icon_date = 0;
        $this->item->save();

        if ($this->logIp)
        {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog('delete', $ip);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function deleteIconForItemDelete(): bool
    {
        $this->deleteIconFiles();

        return true;
    }
    
    /**
     *
     */
    protected function deleteIconFiles()
    {
        if ($this->item->icon_date)
        {
            \XF\Util\File::deleteFromAbstractedPath($this->item->getAbstractedIconPath());
        }
    }

    /**
     * @param string $action
     * @param string $ip
     */
    protected function writeIpLog(string $action, string $ip)
    {
        $item = $this->item;

        /** @var \XF\Repository\Ip $ipRepo */
        $ipRepo = $this->repository('XF:Ip');
        $ipRepo->logIp(\XF::visitor()->user_id, $ip, 'dbtech_shop_item', $item->item_id, 'icon_' . $action);
    }
}