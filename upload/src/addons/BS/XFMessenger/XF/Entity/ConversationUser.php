<?php

namespace BS\XFMessenger\XF\Entity;

use BS\RealTimeChat\Entity\Concerns\UpdateLockable;
use BS\XFMessenger\Broadcasting\Broadcast;
use XF\Mvc\Entity\Structure;

/**
 * @property int $room_wallpaper_date
 * @property array|null $room_wallpaper_options
 */
class ConversationUser extends XFCP_ConversationUser
{
    use UpdateLockable;

    public function getAvatarUser()
    {
        $visitor = \XF::visitor();

        foreach ($this->Master->Recipients as $recipient) {
            if ($recipient->user_id !== $visitor->user_id) {
                return $recipient->User;
            }
        }

        return null;
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isInsert()) {
            Broadcast::newRoom($this);
            return;
        }

        $shouldBroadcastConvUpdate = false;

        if ($this->isChanged('room_wallpaper_date')
            || $this->isChanged('room_wallpaper_options')
        ) {
            $shouldBroadcastConvUpdate = true;
        }

        if ($this->isChanged('is_starred')) {
            $shouldBroadcastConvUpdate = true;
        }

        if ($shouldBroadcastConvUpdate) {
            $this->Master->broadcastUpdate();
        }
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['unread_count'] = ['type' => self::UINT, 'default' => 0];

        $structure->columns['room_wallpaper_date'] = ['type' => self::UINT, 'default' => 0];
        $structure->columns['room_wallpaper_options'] = ['type' => self::JSON_ARRAY, 'default' => []];

        $structure->getters['AvatarUser'] = true;

        return $structure;
    }
}
