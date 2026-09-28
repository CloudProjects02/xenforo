<?php

namespace Olakunlevpn\PostThreadLimitDaily\Service;

use XF\Entity\User;

//TODO refactor this class in the next major version
class DailyLimit
{
    /**
     * @var \XF\App
     */
    protected $app;

    /**
     * Constructor
     *
     * @param \XF\App $app
     */
    public function __construct(\XF\App $app)
    {
        $this->app = $app;
    }

    /**
     * Get the number of threads created by a user today
     *
     * @param User $user
     * @return int
     */
    public function getThreadsCreatedToday(User $user)
    {
        $cutOff = \XF::$time - 86400;
        
        return \XF::db()->fetchOne("
            SELECT COUNT(*)
            FROM xf_thread
            WHERE user_id = ?
                AND post_date >= ?
        ", [$user->user_id, $cutOff]);
    }
    
    /**
     * Get the number of posts created by a user today
     * This method now excludes first posts of threads created today
     *
     * @param User $user
     * @return int
     */
    public function getPostsCreatedToday(User $user)
    {
        $cutOff = \XF::$time - 86400;
        
        // Get only reply posts (position > 0)
        return \XF::db()->fetchOne("
            SELECT COUNT(*)
            FROM xf_post
            WHERE user_id = ?
                AND post_date >= ?
                AND position > 0
        ", [$user->user_id, $cutOff]);
    }
    
    /**
     * Check if a user is excluded from the limit
     *
     * @param User $user
     * @param string $type 'thread' or 'post'
     * @return bool
     */
    public function isExcludedFromLimit(User $user, string $type): bool
    {
        $options = \XF::options();

        $excludedUserIds = explode(',', $options->olakunlevpn_ptld_excludedUserIds);
        if (in_array($user->user_id, $excludedUserIds))
        {
            return true;
        }
        
        if ($options->olakunlevpn_ptld_excludeAdminsMods && ($user->is_admin || $user->is_moderator))
        {
            return true;
        }

        $excludedGroups = $options->olakunlevpn_ptld_excludedUserGroups;
        if ($excludedGroups && $user->isMemberOf($excludedGroups))
        {
            return true;
        }
        
        return false;
    }
    
    /**
     * Calculate the time until the next post/thread is available
     * 
     * @param User $user
     * @param string $type 'thread' or 'post'
     * @return array Time information
     */
    public function getTimeUntilNextAvailable(User $user, string $type): array
    {
        $cutOff = \XF::$time - 86400;
        
        if ($type == 'thread')
        {
            $oldestItem = \XF::db()->fetchOne("
                SELECT post_date
                FROM xf_thread
                WHERE user_id = ?
                    AND post_date >= ?
                ORDER BY post_date ASC
                LIMIT 1
            ", [$user->user_id, $cutOff]);
        }
        else
        {
            // For posts, we need to get the oldest reply post (position > 0)
            $oldestItem = \XF::db()->fetchOne("
                SELECT post_date
                FROM xf_post
                WHERE user_id = ?
                    AND post_date >= ?
                    AND position > 0
                ORDER BY post_date ASC
                LIMIT 1
            ", [$user->user_id, $cutOff]);
        }
        
        if (!$oldestItem)
        {
            return [
                'days' => 0,
                'hours' => 0,
                'minutes' => 0,
                'seconds' => 0,
                'total_seconds' => 0
            ];
        }
        
        $resetTime = $oldestItem + 86400;
        $secondsLeft = max(0, $resetTime - \XF::$time);
        
        $days = floor($secondsLeft / 86400);
        $secondsLeft %= 86400;
        
        $hours = floor($secondsLeft / 3600);
        $secondsLeft %= 3600;
        
        $minutes = floor($secondsLeft / 60);
        $seconds = $secondsLeft % 60;
        
        return [
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'total_seconds' => ($days * 86400) + ($hours * 3600) + ($minutes * 60) + $seconds
        ];
    }
    
    /**
     * Check if user has reached their daily thread limit
     *
     * @param User $user
     * @return bool
     */
    public function hasReachedThreadLimit(User $user): bool
    {
        if ($this->isExcludedFromLimit($user, 'thread'))
        {
            return false;
        }
        
        $options = \XF::options();
        if (!$options->olakunlevpn_ptld_threadLimitEnabled)
        {
            return false;
        }
        
        $threadsToday = $this->getThreadsCreatedToday($user);
        $threadLimit = $options->olakunlevpn_ptld_maxThreadsPerDay;
        
        return $threadsToday >= $threadLimit;
    }
    
    /**
     * Check if user has reached their daily post limit
     * This takes into account that first posts of threads should not count toward the post limit
     *
     * @param User $user
     * @return bool
     */
    public function hasReachedPostLimit(User $user): bool
    {
        if ($this->isExcludedFromLimit($user, 'post'))
        {
            return false;
        }
        
        $options = \XF::options();
        if (!$options->olakunlevpn_ptld_postLimitEnabled)
        {
            return false;
        }
        
        $postsToday = $this->getPostsCreatedToday($user);
        $postLimit = $options->olakunlevpn_ptld_maxPostsPerDay;
        
        return $postsToday >= $postLimit;
    }
}
