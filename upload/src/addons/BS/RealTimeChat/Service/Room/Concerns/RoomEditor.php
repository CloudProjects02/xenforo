<?php

namespace BS\RealTimeChat\Service\Room\Concerns;

use BS\RealTimeChat\Concerns\Repos;
use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Service\Room\Avatar;
use BS\RealTimeChat\Service\Room\Wallpaper;
use BS\RealTimeChat\Utils\RoomTag;
use XF\Entity\User;
use XF\Http\Upload;
use XF\Service\ValidateAndSavableTrait;

trait RoomEditor
{
    use ValidateAndSavableTrait;
    use Repos;

    protected Room $room;

    protected User $user;

    protected Avatar $avatarService;
    protected Wallpaper $wallpaperService;

    protected bool $hasAvatar = false;

    protected bool $deleteAvatar = false;

    public function __construct(
        \XF\App $app,
        ?Room $room = null,
        ?\XF\Entity\User $user = null
    ) {
        parent::__construct($app);

        $this->room = $room ?? $this->em()->create('BS\RealTimeChat:Room');

        if ($user) {
            $this->setUser($user);
        }

        $this->avatarService = $this->service('BS\RealTimeChat:Room\Avatar', $this->room);
        $this->wallpaperService = $this->service('BS\RealTimeChat:Room\Wallpaper', $this->room);
    }

    /**
     * @return \BS\RealTimeChat\Entity\Room
     */
    public function getRoom(): Room
    {
        return $this->room;
    }

    /**
     * @return \XF\Entity\User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    public function setupFromInput(array $input, ?Upload $avatar = null)
    {
        $this->setDescription($input['description']);

        if (! empty($input['tag'])) {
            $this->setTag($input['tag']);
        }

        if (! empty($input['type'])) {
            $this->setType($input['type']);
        }

        if (isset($input['pinned'])) {
            $this->setPinned($input['pinned']);
        }

        if (isset($input['pin_order'])) {
            $this->setPinOrder($input['pin_order']);
        }

        if (isset($input['allow_messages_from_others'])) {
            $this->setAllowedReplies($input['allow_messages_from_others']);
        }

        if ($avatar) {
            $this->hasAvatar = $this->avatarService->setImageFromUpload($avatar);
        } else {
            $this->setDeleteAvatar($input['delete_avatar'] ?? false);
        }
    }

    public function updateWallpaper(
        array $options,
        ?Upload $wallpaper = null,
        ?\XF\Entity\User $forUser = null,
        ?array &$errors = []
    ) {
        if ($forUser) {
            $this->wallpaperService->setUser($forUser);
        }

        $this->wallpaperService->setOptions($options);

        if ($wallpaper) {
            if (! $this->wallpaperService->setImageFromUpload($wallpaper)) {
                $errors = [$this->wallpaperService->getError()];
                return;
            }

            $this->wallpaperService->updateWallpaper();
        } else {
            $this->wallpaperService->updateOptions();
        }

        $this->wallpaperService->setUser(null);
    }

    public function deleteWallpaper(?\XF\Entity\User $forUser = null, array $options = [])
    {
        if ($forUser) {
            $this->wallpaperService->setUser($forUser);
        }

        $this->wallpaperService->deleteWallpaper();

        if ($options) {
            $this->wallpaperService->setOptions($options);
            $this->wallpaperService->updateOptions();
        }

        $this->wallpaperService->setUser(null);
    }

    public function resetWallpaper(?\XF\Entity\User $forUser = null)
    {
        if ($forUser) {
            $this->wallpaperService->setUser($forUser);
        }

        $this->wallpaperService->setOptions([
            'theme_index' => -1,
        ]);
        $this->wallpaperService->deleteWallpaper();
        $this->wallpaperService->updateOptions();

        $this->wallpaperService->setUser(null);
    }

    public function setUser(User $user)
    {
        $this->user = $user;
        $this->room->user_id = $user->user_id;
        $this->room->hydrateRelation('User', $user);
        return $this;
    }

    public function setDescription(string $description)
    {
        $this->room->description = $description;
        return $this;
    }

    public function setTag(string $tag)
    {
        $this->room->tag = RoomTag::convertStrToTag($tag);
        return $this;
    }

    public function setType(string $type)
    {
        $this->room->type = $type;
        return $this;
    }

    public function setPinned(bool $pinned = true)
    {
        $this->room->pinned = $pinned;
        return $this;
    }

    public function setPinOrder(int $pinOrder)
    {
        $this->room->pin_order = $pinOrder;
        return $this;
    }

    public function setAllowedReplies(bool $allowedReplies = true)
    {
        $this->room->allowed_replies = $allowedReplies;
        return $this;
    }

    public function setDeleteAvatar(bool $deleteAvatar = true)
    {
        $this->deleteAvatar = $deleteAvatar;
        return $this;
    }

    protected function _validate()
    {
        $this->room->preSave();
        return $this->room->getErrors();
    }

    protected function _save()
    {
        $db = $this->db();
        $db->beginTransaction();

        $this->room->save(true, false);

        if ($this->hasAvatar) {
            $this->avatarService->updateAvatar();
        }

        if ($this->deleteAvatar) {
            $this->avatarService->deleteAvatar();
        }

        $db->commit();

        $this->afterSave();

        return $this->room;
    }

    protected function afterSave()
    {
    }
}
