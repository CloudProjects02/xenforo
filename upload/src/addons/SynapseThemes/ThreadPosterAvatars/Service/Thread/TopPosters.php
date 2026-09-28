<?php

namespace SynapseThemes\ThreadPosterAvatars\Service\Thread;

use XF\Entity\Thread;
use XF\Service\AbstractService;

class TopPosters extends AbstractService
{
    /** @var \XF\Repository\Post */
    protected $postRepo;

    /** @var \XF\Repository\User */
    protected $userRepo;

    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        
        $this->postRepo = $this->repository('XF:Post');
        $this->userRepo = $this->repository('XF:User');
    }

    public function getTopPosters(Thread $thread)
    {
        if (!$this->app->options()->xfdevs_threadPosters_enabled)
        {
            return [];
        }

        $limit = $this->app->options()->xfdevs_threadPosters_limit;
        
        $db = $this->db();
        
        $topPosters = $db->fetchAllKeyed("
            SELECT 
                user_id,
                COUNT(*) as post_count
            FROM xf_post
            WHERE thread_id = ?
                AND user_id > 0
                AND user_id != ?
                AND message_state = 'visible'
            GROUP BY user_id
            ORDER BY post_count DESC
            LIMIT ?
        ", 'user_id', [$thread->thread_id, $thread->user_id, $limit]);

        if (!$topPosters)
        {
            return [];
        }

        return $this->userRepo->getUsersByIdsOrdered(array_keys($topPosters));
    }
} 