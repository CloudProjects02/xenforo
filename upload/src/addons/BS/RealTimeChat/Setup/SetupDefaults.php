<?php

namespace BS\RealTimeChat\Setup;

use BS\RealTimeChat\Utils\RoomTag;

trait SetupDefaults
{
    public function installStep4()
    {
        // insert public room
        $this->db()->insert(
            'xf_bs_chat_room',
            [
                'type' => 'public',
                'tag' => RoomTag::DEFAULT_TAG,
                'description' => '',
                'avatar_date' => 0,
                'members_count' => 0,
                'last_message_id' => 0,
                'last_message_date' => 0,
                'last_message_user_id' => 0,
                'pinned' => 1,
                'pin_order' => 0,
                'created_date' => \XF::$time,
            ]
        );
    }

    public function postInstall(array &$stateChanges)
    {
        $this->createWelcomeMessage();
    }

    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        // 2.0.0
        if ($previousVersion < 2000070) {
            $this->createWelcomeMessage();
        }
    }

    protected function createWelcomeMessage()
    {
        /** @var \BS\RealTimeChat\Entity\Room $room */
        $room = \XF::em()->findOne(
            'BS\RealTimeChat:Room',
            [
                'type' => 'public',
                'tag'  => RoomTag::DEFAULT_TAG,
                'user_id' => 0
            ]
        );
        if (!$room) {
            return;
        }

        $creator = $this->getMessageRepo()->setupSystemMessageCreator(
            $room,
            \XF::phrase('rtc_welcome_message')
        );
        $creator->save();
    }

    /**
     * @return \XF\Mvc\Entity\Repository|\BS\RealTimeChat\Repository\Message
     */
    protected function getMessageRepo()
    {
        return \XF::repository('BS\RealTimeChat:Message');
    }
}
