<?php

namespace SynapseThemes\ThreadReadTime\XF\Entity;

class Post extends XFCP_Post
{
    /**
     * @return string
     */
    protected function _getReadTimeMinutes()
    {
        /** @var \XF\Entity\Thread $thread */
        $thread = $this->Thread;
        if (!$thread)
        {
            return '1';
        }

        $readTime = $thread->get('synapse_read_time');
        if ($readTime === null)
        {
            return '1';
        }

        return (string)max(1, intval($readTime));
    }
} 