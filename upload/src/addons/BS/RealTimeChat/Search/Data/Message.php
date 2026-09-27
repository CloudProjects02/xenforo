<?php

namespace BS\RealTimeChat\Search\Data;

use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;

class Message extends AbstractData
{
    public function getIndexData(Entity $message)
    {
        if (! $message->isSearchable()) {
            return null;
        }

        return IndexRecord::create('chat_message', $message->message_id, [
            'title'         => $message->room_tag,
            'message'       => $message->message,
            'date'          => $message->message_date,
            'user_id'       => $message->user_id,
            'discussion_id' => $message->room_id,
            'metadata'      => [
                'room_type' => $message->Room->type,
            ]
        ]);
    }

    public function setupMetadataStructure(MetadataStructure $structure)
    {
        $structure->addField('room_type', MetadataStructure::STR);
    }

    public function getTypePermissionConstraints(\XF\Search\Query\Query $query, $isOnlyType)
    {
        if (! $isOnlyType) {
            return [];
        }

        return [
            new MetadataConstraint('room_type', 'public')
        ];
    }

    public function getResultDate(Entity $message)
    {
        return $message->message_date;
    }

    public function getTemplateData(Entity $message, array $options = [])
    {
        return compact('message', 'options');
    }

    public function canUseInlineModeration(Entity $message, &$error = null)
    {
        return false;
    }
}
