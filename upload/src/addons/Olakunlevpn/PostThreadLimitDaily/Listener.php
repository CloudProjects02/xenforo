<?php

namespace Olakunlevpn\PostThreadLimitDaily;

use XF\Mvc\Entity\Entity;
use XF\Pub\Controller\Forum;
use XF\Pub\Controller\Thread;
use Olakunlevpn\PostThreadLimitDaily\Service\DailyLimit;

//TODO refactor this class in the next major version
class Listener
{
    /**
     * Check thread- and post-limits before saving
     */
    public static function entityPreSave(Entity $entity)
    {
        $options = \XF::options();
        $visitor = \XF::visitor();

        if (!$visitor->user_id)
        {
            return;
        }

        /** @var DailyLimit $dailyLimitService */
        $dailyLimitService = \XF::service('Olakunlevpn\PostThreadLimitDaily:DailyLimit');

        if ($entity instanceof \XF\Entity\Thread && $options->olakunlevpn_ptld_threadLimitEnabled)
        {
            if ($dailyLimitService->isExcludedFromLimit($visitor, 'thread'))
            {
                return;
            }

            $threadsToday = $dailyLimitService->getThreadsCreatedToday($visitor);
            $threadLimit = $options->olakunlevpn_ptld_maxThreadsPerDay;

            if ($threadsToday >= $threadLimit)
            {
                $entity->error(\XF::phrase('olakunlevpn_ptld_thread_limit_reached', ['limit' => $threadLimit]));
            }
        }
        else if ($entity instanceof \XF\Entity\Post && $entity->isFirstPost() == false && $options->olakunlevpn_ptld_postLimitEnabled)
        {
            if ($dailyLimitService->isExcludedFromLimit($visitor, 'post'))
            {
                return;
            }

            $postsToday = $dailyLimitService->getPostsCreatedToday($visitor);
            $postLimit = $options->olakunlevpn_ptld_maxPostsPerDay;

            if ($postsToday >= $postLimit)
            {
                $entity->error(\XF::phrase('olakunlevpn_ptld_post_limit_reached', ['limit' => $postLimit]));
            }
        }
    }

    /**
     * Check thread and post limits before controller actions
     *
     * @param \XF\Mvc\Controller $controller
     * @param string $action
     * @param \XF\Mvc\ParameterBag $params
     */
    public static function controllerPreDispatch(\XF\Mvc\Controller $controller, $action, \XF\Mvc\ParameterBag $params)
    {
        $options = \XF::options();
        $visitor = \XF::visitor();
        
        if (!$visitor->user_id)
        {
            return;
        }
        
        $controllerClass = get_class($controller);
        
        /** @var DailyLimit $dailyLimitService */
        $dailyLimitService = \XF::service('Olakunlevpn\PostThreadLimitDaily:DailyLimit');
        
        $container = \XF::app()->container();
        
        if (($controller instanceof Forum || strpos($controllerClass, 'Forum') !== false) && $options->olakunlevpn_ptld_threadLimitEnabled)
        {
            if (!$dailyLimitService->isExcludedFromLimit($visitor, 'thread'))
            {
                // Check if the user has reached their thread limit
                if ($dailyLimitService->hasReachedThreadLimit($visitor))
                {
                    if ($action == 'AddThread' || $action == 'AddThreadInline')
                    {
                        $timeInfo = $dailyLimitService->getTimeUntilNextAvailable($visitor, 'thread');
                        
                        $reply = $controller->error(\XF::phrase('olakunlevpn_ptld_thread_limit_reached_error', [
                            'limit' => $options->olakunlevpn_ptld_maxThreadsPerDay,
                            'hours' => $timeInfo['hours'],
                            'minutes' => $timeInfo['minutes']
                        ]));
                        
                        throw new \XF\Mvc\Reply\Exception($reply);
                    }
                }
                
                // Store thread limit data for templates
                $threadsToday = $dailyLimitService->getThreadsCreatedToday($visitor);
                $threadLimit = $options->olakunlevpn_ptld_maxThreadsPerDay;
                $remainingThreads = max(0, $threadLimit - $threadsToday);
                $timeInfo = $dailyLimitService->getTimeUntilNextAvailable($visitor, 'thread');

                $container['ptld.threadData'] = [
                    'remaining' => $remainingThreads,
                    'reached' => ($remainingThreads <= 0),
                    'max' => $threadLimit,
                    'timeInfo' => $timeInfo,
                    'nearLimit' => (($threadsToday / $threadLimit) * 100) >= 80
                ];
            }
        }
        
        if (($controller instanceof Thread || strpos($controllerClass, 'Thread') !== false) && $options->olakunlevpn_ptld_postLimitEnabled)
        {
            if (!$dailyLimitService->isExcludedFromLimit($visitor, 'post'))
            {
                // Check if the user has reached their post limit
                if ($dailyLimitService->hasReachedPostLimit($visitor))
                {
                    if ($action == 'AddReply' || $action == 'AddReplyPreview')
                    {
                        $timeInfo = $dailyLimitService->getTimeUntilNextAvailable($visitor, 'post');
                        
                        $reply = $controller->error(\XF::phrase('olakunlevpn_ptld_post_limit_reached', [
                            'limit' => $options->olakunlevpn_ptld_maxPostsPerDay
                        ]));
                        
                        throw new \XF\Mvc\Reply\Exception($reply);
                    }
                }
                
                // Store post limit data for templates
                $postsToday = $dailyLimitService->getPostsCreatedToday($visitor);
                $postLimit = $options->olakunlevpn_ptld_maxPostsPerDay;
                $remainingPosts = max(0, $postLimit - $postsToday);
                $timeInfo = $dailyLimitService->getTimeUntilNextAvailable($visitor, 'post');

                $container['ptld.postData'] = [
                    'remaining' => $remainingPosts,
                    'reached' => ($remainingPosts <= 0),
                    'max' => $postLimit,
                    'timeInfo' => $timeInfo,
                    'nearLimit' => (($postsToday / $postLimit) * 100) >= 80
                ];
            }
        }
    }
    
    /**
     * Handle template rendering to inject thread/post limit information
     *
     * @param \XF\Template\Templater $templater
     * @param string $type
     * @param string $template
     * @param array $params
     */
    public static function templateRender(\XF\Template\Templater $templater, &$type, &$template, &$params)
    {
        $threadTemplates = ['forum_view', 'forum_post_quick_thread', 'forum_post_thread'];
        $postTemplates = ['thread_view'];
        
        if (in_array($template, $threadTemplates) || in_array($template, $postTemplates))
        {
            $container = \XF::app()->container();
            $options = \XF::options();
            $visitor = \XF::visitor();
            
            // Skip processing if the addon is disabled
            if (!$options->olakunlevpn_ptld_threadLimitEnabled && !$options->olakunlevpn_ptld_postLimitEnabled)
            {
                return;
            }
            
            // Skip processing if the user is an admin or in an excluded group
            /** @var DailyLimit $dailyLimitService */
            $dailyLimitService = \XF::service('Olakunlevpn\PostThreadLimitDaily:DailyLimit');
            if ($dailyLimitService->isExcludedFromLimit($visitor, 'thread') && $dailyLimitService->isExcludedFromLimit($visitor, 'post'))
            {
                return;
            }

            if (in_array($template, $threadTemplates) && isset($container['ptld.threadData']))
            {
                $threadData = $container['ptld.threadData'];
                
                $params['olakunlevpn_ptldRemainingThreads'] = $threadData['remaining'];
                $params['olakunlevpn_ptldThreadLimitReached'] = $threadData['reached'];
                $params['olakunlevpn_ptldMaxThreads'] = $threadData['max'];
                $params['timeInfo'] = [
                    'days' => $threadData['timeInfo']['days'],
                    'hours' => $threadData['timeInfo']['hours'],
                    'minutes' => $threadData['timeInfo']['minutes'],
                    'seconds' => $threadData['timeInfo']['seconds'],
                    'total_seconds' => $threadData['timeInfo']['total_seconds']
                ];
                $params['olakunlevpn_ptldNearLimit'] = $threadData['nearLimit'];

            }
            else if (in_array($template, $postTemplates) && isset($container['ptld.postData']))
            {
                $postData = $container['ptld.postData'];
                
                $params['olakunlevpn_ptldRemainingPosts'] = $postData['remaining'];
                $params['olakunlevpn_ptldPostLimitReached'] = $postData['reached'];
                $params['olakunlevpn_ptldMaxPosts'] = $postData['max'];
                $params['timeInfo'] = [
                    'days' => $postData['timeInfo']['days'],
                    'hours' => $postData['timeInfo']['hours'],
                    'minutes' => $postData['timeInfo']['minutes'],
                    'seconds' => $postData['timeInfo']['seconds'],
                    'total_seconds' => $postData['timeInfo']['total_seconds']
                ];
                $params['olakunlevpn_ptldNearLimit'] = $postData['nearLimit'];
            }
        }
    }
}
