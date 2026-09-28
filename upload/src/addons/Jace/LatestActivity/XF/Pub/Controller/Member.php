<?php

namespace Jace\LatestActivity\XF\Pub\Controller;

use XF\Entity\Post;
use XF\Entity\User;
use XF\Entity\UserProfile;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use function array_slice;

class Member extends XFCP_Member
{
    public function actionView(ParameterBag $params)
    {
        $parent = parent::actionView($params);

        $visitor = \xf::visitor();
        $nodes = \XF::options()->jace_userlatestactivity_allow;

        $nodes = array_filter($nodes);

        $userId = null;

        if (!$params->user_id) {
            $method = \xf::app()
                ->request()
                ->getRoutePath();

            $username = array_shift($method);

            $username = urldecode($username);

            $user = $this->finder("XF:User")
                ->where("username", $username)
                ->fetchOne();

            if ($user) {
                $userId = $user->user_id;
            }
        } else {
            $userId = $params->user_id;
        }

        if (isset($nodes) && count($nodes) && $userId) {
            $latestPostsUserCollection = $this->finder("XF:Post")
                ->with(["Thread", "Thread.Forum", "User"])
                ->where("user_id", $userId)
                ->where('message_state', '=', 'visible') // Check if the post is visible
                ->order("post_date", "DESC")
                ->fetch(\xf::options()->jace_no_of_latestactivtiydisplay);

                $latestPostsUser = [];

                foreach ($latestPostsUserCollection as $post) {
                    // Check if the post has a thread associated with it
                    if ($post->Thread) {
                        // Extract necessary data from the post, including post_id
                        $postData = [
                            'Thread' => $post->Thread,
                            'message_state' => $post->message_state,
                            'post_date' => $post->post_date,
                            'post_id' => $post->post_id, // Include post_id
                            'category_name' => $post->Thread->Forum ? $post->Thread->Forum->title : '',
                        ];
                
                        $latestPostsUser[] = $postData;
                    }
                }
                
            // Filter out deleted or non-visible posts and those without a thread title or post date
            $latestPostsUser = array_filter($latestPostsUser, function($post) {
                return $post['Thread'] && $post['message_state'] === 'visible' && $post['Thread']->title && $post['post_date'];
            });

            if ($parent instanceof \XF\Mvc\Reply\View) {
                $parent->setParam("latestPostsActivity", $latestPostsUser);
            }
        }

        return $parent;
    }
}
