<?php

namespace XC\RankingSystem\XF\Pub\Controller;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class Thread extends XFCP_Thread {

    protected function finalizeThreadReply(\XF\Service\Thread\Replier $replier) {



        $parent = parent::finalizeThreadReply($replier);

        $serviceGeneral = $this->service('XC\RankingSystem:General');

        $thread = $replier->getThread();

        $post = $replier->getPost();
        
        if($thread->user_id!=$post->user_id){
            
            $thread->fastUpdate('rank_reply_count', $thread->rank_reply_count + 1);
        }
       
        if ($serviceGeneral->isAllowForumsXp($thread->node_id)) {

            $XpGeneral = $this->service('XC\RankingSystem:ExperienctPoint');

            $ThreadXp = $XpGeneral->checkXp("create_post");

            $visitor = \xf::visitor();

            if ($ThreadXp && $visitor->user_id) {

                $XpGeneral->AwardXPToUser($ThreadXp, $visitor, $post->post_id,true);
            }
            
            
            $xpThreadReply = $XpGeneral->checkXp("x_thread_replies");
            
            if($xpThreadReply){
                
                if($thread->rank_reply_count > $xpThreadReply->point_depend || $thread->rank_reply_count == $xpThreadReply->point_depend){
                    
                    $XpGeneral->AwardXPToUser($xpThreadReply, $thread->User, $thread->thread_id,true);
                    $thread->fastUpdate('rank_reply_count', 0);
                }
            }

        }
        
       
        

        return $parent;
    }

    public function actionIndex(ParameterBag $params) {
        $parent = parent::actionIndex($params);

        if (\XF::options()->xc_show_progress_posts) {
            $GLOBALS['allow_to_progressbar'] = true;
        }

        return $parent;
    }

    protected function getNewPostsReply(\XF\Entity\Thread $thread, $lastDate) {
        $parent = parent::getNewPostsReply($thread, $lastDate);

        if (\XF::options()->xc_show_progress_posts) {
            $GLOBALS['allow_to_progressbar'] = true;
        }

        return $parent;
    }

    protected function getNewPostsReplyInternal(
            \XF\Entity\Thread $thread,
            AbstractCollection $posts,
            \XF\Entity\Post $firstUnshownPost = null
    ) {
        if (\XF::options()->xc_show_progress_posts) {
            $GLOBALS['allow_to_progressbar'] = true;
        }
        return parent::getNewPostsReplyInternal($thread, $posts, $firstUnshownPost);
    }

}
