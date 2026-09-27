<?php

namespace BS\RealTimeChat;

use BS\RealTimeChat\Entity\Message;
use BS\RealTimeChat\Entity\Room;
use BS\RealTimeChat\Utils\RoomTag;

class Route
{
    public static function buildLinkWithTag(
        &$prefix,
        &$route,
        &$action,
        &$data,
        &$parameters,
        $router
    ) {
        $updatedData = [];

        if ($data instanceof Room) {
            $updatedData = [
                'tag' => RoomTag::urlEncode($data->tag)
            ];
        }

        if ($data instanceof Message) {
            $updatedData = [
                'tag' => RoomTag::urlEncode($data->room_tag),
                'message_id' => $data->message_id
            ];
        }

        if (is_array($data) && isset($data['tag'])) {
            $updatedData = array_merge($data, [
                'tag' => RoomTag::urlEncode($data['tag'])
            ]);
        }

        if (! empty($updatedData)) {
            $data = $updatedData;
        }
    }
}