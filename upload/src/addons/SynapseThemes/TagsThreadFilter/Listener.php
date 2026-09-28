<?php

namespace SynapseThemes\TagsThreadFilter;

class Listener
{
    public static function templaterTemplatePreRender(\XF\Template\Templater $templater, &$type, &$template, array &$params)
    {
        if ($type !== 'public')
        {
            return;
        }

        $app = \XF::app();

        if ($template === 'forum_view')
        {
            if (isset($params['forum']) && $params['forum'] instanceof \XF\Entity\Forum)
            {
                /** @var \XF\Entity\Forum $forum */
                $forum = $params['forum'];

                $db = $app->db();

                $tagIds = $db->fetchAllColumn("
                    SELECT DISTINCT tc.tag_id
                    FROM xf_tag_content AS tc
                    INNER JOIN xf_thread AS th ON (th.thread_id = tc.content_id AND tc.content_type = 'thread')
                    WHERE th.node_id = ?
                      AND th.discussion_state = 'visible'
                ", $forum->node_id);

                $tags = [];
                if ($tagIds)
                {
                    $tags = $app->finder('XF:Tag')
                                ->whereIds($tagIds)
                                ->order('tag', 'ASC')
                                ->fetch();
                }

                if (!empty($tags))
                {
                    $params['synapseThreadTags'] = $tags;
                }
            }
        }
        else if ($template === 'thread_view')
        {
            if (isset($params['thread']) && $params['thread'] instanceof \XF\Entity\Thread)
            {
                /** @var \XF\Entity\Thread $thread */
                $thread = $params['thread'];

                if ($thread->tags && $thread->tags instanceof \XF\Mvc\Entity\AbstractCollection && !$thread->tags->isEmpty())
                {
                    $params['synapseThreadTags'] = $thread->tags;
                }
            }
        }
    }
}