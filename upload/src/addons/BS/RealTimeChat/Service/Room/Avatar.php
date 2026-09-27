<?php

namespace BS\RealTimeChat\Service\Room;

use BS\RealTimeChat\Entity\Room;
use XF\Service\AbstractService;

class Avatar extends AbstractService
{
    protected Room $room;

    protected ?string $fileName;

    protected $width;
    protected $height;

    protected string $type;

    protected $error = null;

    protected array $allowedTypes = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG];

    protected array $sizeMap;

    protected bool $throwErrors = true;

    public function __construct(\XF\App $app, Room $room)
    {
        parent::__construct($app);
        $this->room = $room;

        $this->sizeMap = $this->app->container('avatarSizeMap');
    }

    public function silentRunning($runSilent)
    {
        $this->throwErrors = ! $runSilent;
    }

    public function setImage($fileName)
    {
        if (! $this->validateImageAsAvatar($fileName, $error)) {
            $this->error = $error;
            $this->fileName = null;
            return false;
        }

        $this->fileName = $fileName;
        return true;
    }

    public function setImageFromUpload(\XF\Http\Upload $upload)
    {
        $upload->requireImage();

        if (! $upload->isValid($errors)) {
            $this->error = reset($errors);
            return false;
        }

        return $this->setImage($upload->getTempFile());
    }

    public function setImageFromExisting()
    {
        $path = $this->room->getAbstractedCustomAvatarPath('o');
        if (! $this->app->fs()->has($path)) {
            return $this->throwException(
                new \InvalidArgumentException("Room does not have an 'o' avatar ($path)")
            );
        }

        $tempFile = \XF\Util\File::copyAbstractedPathToTempFile($path);
        return $this->setImage($tempFile);
    }

    public function validateImageAsAvatar($fileName, &$error = null)
    {
        $error = null;

        if (! file_exists($fileName)) {
            return $this->throwException(
                new \InvalidArgumentException("Invalid file '$fileName' passed to avatar service")
            );
        }
        if (! is_readable($fileName)) {
            return $this->throwException(
                new \InvalidArgumentException("'$fileName' passed to avatar service is not readable")
            );
        }

        $imageInfo = filesize($fileName) ? @getimagesize($fileName) : false;
        if (! $imageInfo) {
            $error = \XF::phrase('provided_file_is_not_valid_image');
            return false;
        }

        $type = $imageInfo[2];
        if (! in_array($type, $this->allowedTypes, true)) {
            $error = \XF::phrase('provided_file_is_not_valid_image');
            return false;
        }

        [$width, $height] = $imageInfo;

        if (! $this->app->imageManager()->canResize($width, $height)) {
            $error = \XF::phrase('uploaded_image_is_too_big');
            return false;
        }

        $this->width = $width;
        $this->height = $height;
        $this->type = $type;

        return true;
    }

    public function updateAvatar()
    {
        if (! $this->fileName) {
            return $this->throwException(new \LogicException("No source file for avatar set"));
        }
        if (! $this->room->exists()) {
            return $this->throwException(new \LogicException("Room does not exist, cannot update avatar"));
        }

        $imageManager = $this->app->imageManager();

        $outputFiles = [];
        $baseFile = $this->fileName;

        $origSize = $this->sizeMap['o'];
        $shortSide = min($this->width, $this->height);

        if ($shortSide > $origSize) {
            $image = $imageManager->imageFromFile($this->fileName);
            if (! $image) {
                return false;
            }

            $image->resizeShortEdge($origSize);

            $newTempFile = \XF\Util\File::getTempFile();
            if ($newTempFile && $image->save($newTempFile, null, 95)) {
                $outputFiles['o'] = $newTempFile;
                $baseFile = $newTempFile;
            } else {
                return $this->throwException(
                    new \RuntimeException(
                        "Failed to save image to temporary file; check internal_data/data permissions"
                    )
                );
            }

            unset($image);
        } else {
            $outputFiles['o'] = $this->fileName;
        }

        foreach ($this->sizeMap as $code => $size) {
            if (isset($outputFiles[$code])) {
                continue;
            }

            $image = $imageManager->imageFromFile($baseFile);
            if (! $image) {
                continue;
            }

            $newTempFile = \XF\Util\File::getTempFile();
            if ($newTempFile && $image->save($newTempFile)) {
                $outputFiles[$code] = $newTempFile;
            }
            unset($image);
        }

        if (count($outputFiles) !== count($this->sizeMap)) {
            return $this->throwException(
                new \RuntimeException(
                    "Failed to save image to temporary file; image may be corrupt or check internal_data/data permissions"
                )
            );
        }

        foreach ($outputFiles as $code => $file) {
            $dataFile = $this->room->getAbstractedCustomAvatarPath($code);
            \XF\Util\File::copyFileToAbstractedPath($file, $dataFile);
        }

        $this->room->fastUpdate('avatar_date', \XF::$time);

        return true;
    }

    public function deleteAvatar()
    {
        $this->deleteAvatarFiles();

        $this->room->fastUpdate('avatar_date', 0);

        return true;
    }

    public function deleteAvatarForRoomDelete()
    {
        $this->deleteAvatarFiles();

        return true;
    }

    protected function deleteAvatarFiles()
    {
        if (! $this->room->avatar_date) {
            return;
        }

        foreach ($this->sizeMap as $code => $size) {
            \XF\Util\File::deleteFromAbstractedPath($this->room->getAbstractedCustomAvatarPath($code));
        }
    }

    /**
     * @param  \Exception  $error
     *
     * @return bool
     * @throws \Exception
     */
    protected function throwException(\Exception $error)
    {
        if ($this->throwErrors) {
            throw $error;
        }

        return false;
    }
}