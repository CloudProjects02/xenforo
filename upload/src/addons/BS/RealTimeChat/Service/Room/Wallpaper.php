<?php

namespace BS\RealTimeChat\Service\Room;

use BS\RealTimeChat\DB;
use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Job\RoomMember\UpdateWallpaper;
use XF\Service\AbstractService;
use XF\Util\File;

use function Clue\StreamFilter\fun;

class Wallpaper extends AbstractService
{
    protected Room $room;
    protected ?\XF\Entity\User $user = null;

    protected array $options = [
        'blurred' => false
    ];

    protected ?string $fileName;

    protected $width;
    protected $height;

    protected string $type;

    protected $error = null;

    protected array $allowedTypes = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG];

    protected bool $throwErrors = true;

    public function __construct(
        \XF\App $app,
        Room $room,
        ?\XF\Entity\User $user = null
    ) {
        parent::__construct($app);
        $this->room = $room;
        $this->user = $user;
    }

    public function silentRunning($runSilent)
    {
        $this->throwErrors = ! $runSilent;
    }

    public function setImage($fileName)
    {
        if (! $this->validateImageAsWallpaper($fileName, $error)) {
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

    public function validateImageAsWallpaper($fileName, &$error = null)
    {
        $error = null;

        if (! file_exists($fileName)) {
            return $this->throwException(
                new \InvalidArgumentException("Invalid file '$fileName' passed to wallpaper service")
            );
        }
        if (! is_readable($fileName)) {
            return $this->throwException(
                new \InvalidArgumentException("'$fileName' passed to wallpaper service is not readable")
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

        $sizeLimit = $this->app->options()->realTimeChatMinWallpaperSize;

        if ($width < $sizeLimit['width'] || $height < $sizeLimit['height']) {
            $error = \XF::phrase('rtc_please_upload_image_that_is_at_least_x_and_y', $sizeLimit);
            return false;
        }

        $this->width = $width;
        $this->height = $height;
        $this->type = $type;

        return true;
    }

    public function updateWallpaper()
    {
        if (! $this->fileName) {
            return $this->throwException(new \LogicException("No source file for wallpaper set"));
        }
        if (! $this->room->exists()) {
            return $this->throwException(new \LogicException("Room does not exist, cannot update avatar"));
        }

        $imageManager = $this->app->imageManager();

        $baseFile = $this->fileName;

        $image = $imageManager->imageFromFile($baseFile);
        if (! $image) {
            throw new \LogicException("Could not load image from file");
        }

        $image = $image->resize(1920, 1080, false);

        $tempFile = File::getTempFile();
        $image->save($tempFile);

        $wallpaperPath = $this->room->getAbstractedCustomWallpaperPath($this->user->user_id ?? null);

        File::copyFileToAbstractedPath($tempFile, $wallpaperPath);

        $this->updateWallpaperDate(\XF::$time);

        return true;
    }

    public function deleteWallpaper()
    {
        $this->deleteWallpaperFiles();

        $this->updateWallpaperDate(0);

        return true;
    }

    public function updateOptions()
    {
        if ($this->user) {
            $member = $this->room->getMember($this->user);

            DB::repeatOnDeadlock(function () use ($member) {
                $member->lockForUpdate();

                $member->room_wallpaper_options = $this->options;
                $member->saveIfChanged();
            }, function () use ($member) {
                UpdateWallpaper::create($member->room_id, $member->user_id, [
                    'options' => $this->options
                ]);
            });
            return;
        }

        $this->room->wallpaper_options = $this->options;
        $this->room->saveIfChanged();
    }

    protected function updateWallpaperDate(int $date)
    {
        if ($this->user) {
            $member = $this->room->getMember($this->user);

            DB::repeatOnDeadlock(function () use ($member, $date) {
                $member->lockForUpdate();

                $member->room_wallpaper_date = $date;
                $member->room_wallpaper_options = $this->options;
                $member->saveIfChanged();
            }, function () use ($member, $date) {
                UpdateWallpaper::create($member->room_id, $member->user_id, [
                    'date' => $date,
                    'options' => $this->options
                ]);
            });
            return;
        }

        $this->room->wallpaper_date = $date;
        $this->room->wallpaper_options = $this->options;
        $this->room->saveIfChanged();
    }

    public function deleteWallpapersForRoomDelete()
    {
        $this->deleteWallpaperFiles(true);

        return true;
    }

    protected function deleteWallpaperFiles(bool $deleteAll = false)
    {
        $userIds = [];

        if ($this->user) {
            $userIds[] = $this->user->user_id;
        } else {
            $userIds[] = null;
        }

        if ($deleteAll) {
            $membersFinder = $this->finder('BS\RealTimeChat:RoomMember')
                ->where('room_id', $this->room->room_id)
                ->where('room_wallpaper_date', '>', 0);
            $userIds = array_column($membersFinder->fetchColumns('user_id'), 'user_id');
        }

        foreach ($userIds as $memberId) {
            File::deleteFromAbstractedPath($this->room->getAbstractedCustomWallpaperPath($memberId));
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

    /**
     * @param  \XF\Entity\User|null  $user
     */
    public function setUser(?\XF\Entity\User $user): void
    {
        $this->user = $user;
    }

    /**
     * @return null
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param  array  $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
