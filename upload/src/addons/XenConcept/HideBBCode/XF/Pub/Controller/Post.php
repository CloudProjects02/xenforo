<?php

/*************************************************************************
 * Hide BBCode - XenConcept (c) 2020
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class Post extends XFCP_Post
{
    /**
     * @param ParameterBag $params
     * @return View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionCheckHide(ParameterBag $params)
    {
        $post   = $this->assertViewablePost($params->post_id);
        $thread = $post->Thread;

        $threadPlugin = $this->plugin('XF:Thread');
        $threadPlugin->fetchExtraContentForPostsFullView([$post->post_id => $post], $thread);

        $typeHandler = $thread->TypeHandler;

        $viewParams = [
            'post' => $post,
            'thread' => $thread,
            'isPinnedFirstPost' => $post->isFirstPost() && $typeHandler->isFirstPostPinned($thread),
            'templateOverrides' => $typeHandler->getThreadViewTemplateOverrides($thread)
        ];

        if ($this->getHideBbCodeRepo()->addonUwFcsIsActive() /** && !$post->isFirstPost() */)
        {
            $viewParams = $this->applyUwFcsCommentsToViewParams($viewParams, $post, $thread);
        }

        return $this->view('XF:Post\CheckHide', 'xc_hide_bbcode_post_check_hide', $viewParams);
    }

    protected function applyUwFcsCommentsToViewParams($viewParams, $post, $thread)
    {
        $status = [1];

        $visitor = \XF::visitor();

        if($visitor->hasNodePermission($thread->node_id, 'uw_fcs_view_deleted_comme'))
        {
            $status[] = 2;
        }

        $limit = \XF::options()->uw_limit_per_post;

        $commentsFinder = $this->finder('UW\FCS:Comment')
            ->where('post_id',$post->post_id)
            ->with('User')
            ->where('status',$status)
            ->order('comment_date','DESC');

        $commentsCount = $commentsFinder->total();
        $comments = $commentsFinder->limit($limit)->fetch()->reverse();

        $sortedComments = [];
        foreach ($comments as $comment)
        {
            $sortedComments[] = $comment;
        }

        $viewParams['comments'] = $sortedComments;
        $viewParams['commentsCount'] = $commentsCount;

        return $viewParams;
    }

    /**
     * @return \XenConcept\HideBBCode\Repository\HideBbCode
     */
    protected function getHideBbCodeRepo()
    {
        return \XF::repository('XenConcept\HideBBCode:HideBbCode');
    }
}