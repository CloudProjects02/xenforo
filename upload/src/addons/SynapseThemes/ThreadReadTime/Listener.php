<?php

namespace SynapseThemes\ThreadReadTime;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Listener
{
    const TO_ONE = 'to_one';

    public static function entityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        if ($structure->shortName == 'XF:Thread')
        {
            $structure->columns['synapse_read_time'] = [
                'type' => Entity::UINT,
                'default' => 0,
                'required' => true
            ];
            
            $structure->getters['synapse_read_time_formatted'] = true;
        }
        else if ($structure->shortName == 'XF:Post')
        {
            $structure->getters += [
                'read_time_minutes' => ['getter' => '_getReadTimeMinutes', 'cache' => false]
            ];
            
            if (!isset($structure->relations['Thread']))
            {
                $structure->relations['Thread'] = [
                    'entity' => 'XF:Thread',
                    'type' => self::TO_ONE,
                    'conditions' => 'thread_id',
                    'primary' => true
                ];
            }
        }
    }

    public static function postSave(\XF\Entity\Post $post)
    {
        if ($post->isFirstPost() && $post->isChanged('message'))
        {
            $thread = $post->Thread;
            if ($thread)
            {
                $thread->updateReadTime();
            }
        }
    }
} 