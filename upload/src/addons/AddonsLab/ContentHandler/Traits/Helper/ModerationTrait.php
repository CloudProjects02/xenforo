<?php

namespace AddonsLab\ContentHandler\Traits\Helper;

use XF\Entity\Forum;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Entity\User;

trait ModerationTrait
{
    /**
     * @var User
     */
    protected $viewingUser;

    public function closeThread($thread)
    {
        if ($thread = $this->getNormalizedThreadObject($thread))
        {
            if ((bool)$thread->discussion_open !== true)
            {
                return false;
            }

            $thread->discussion_open = 0;

            $thread->save();

            return true;
        }

        return false;
    }

    public function moveThread($thread, $destinationForumId, $alert = false, $alert_reason = null)
    {
        if (!$destinationForumId)
        {
            return false;
        }

        if ($thread = $this->getNormalizedThreadObject($thread))
        {
            if ((int)$thread['node_id'] === (int)$destinationForumId)
            {
                return false;
            }

            $type = 'thread';

            /** @var \XF\InlineMod\Thread $handler */
            $handler = $this->getInlineModHandler($type);
            if (!$handler)
            {
                return false;
            }

            /** @var \XF\InlineMod\Thread\Move $actionHandler */
            $actionHandler = $handler->getAction('move');

            if (!$actionHandler)
            {
                return false;
            }

            $collection = new \XF\Mvc\Entity\ArrayCollection([$thread]);
            $options = ['target_node_id' => $destinationForumId, 'alert' => $alert, 'alert_reason' => $alert_reason];
            /*if(!$actionHandler->canApply($collection, $options)) {
                return false;
            }*/

            try
            {
                $actionHandler->apply($collection, $options);
            } catch (\Exception $exception)
            {
                // any error happened during the move will be just ignored
                return false;
            }


            return true;
        }

        return false;
    }

    /**
     * @param $thread
     * @throws \XF\PrintableException
     */
    public function deleteThread($thread)
    {
        if ($thread = $this->getNormalizedThreadObject($thread))
        {
            if ($thread->isDeleted())
            {
                return false;
            }

            if ($thread->hasChanges())
            {
                $thread->reset();
            }

            $thread->delete();

            return true;
        }

        return false;
    }

    /**
     * @param $post
     * @throws \XF\PrintableException
     */
    public function deletePost($post)
    {
        if ($post = $this->getNormalizedPostObject($post))
        {
            if ($post->isDeleted())
            {
                return;
            }
            if ($post->hasChanges())
            {
                $post->reset();
            }
            $post->delete();
        }
    }

    /**
     * @param $thread
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function markThreadAsModerated($thread)
    {
        if ($thread = $this->getNormalizedThreadObject($thread))
        {
            $this->setThreadState($thread, 'moderated');
        }
    }

    /**
     * @param $post
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function markPostAsModerated($post)
    {
        if ($post = $this->getNormalizedPostObject($post))
        {
            $this->setPostState($post, 'moderated');
        }
    }

    /**
     * @param Thread $thread
     * @param $newState
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function setThreadState(Thread $thread, $newState)
    {
        $thread->discussion_state = $newState;
        $thread->save();
    }

    /**
     * @param Post $post
     * @param $newState
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function setPostState(Post $post, $newState)
    {
        $post->message_state = $newState;
        $post->save();
    }

    /**
     * @param $thread
     * @param $forum
     * @param bool $followForumRules
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function markThreadAsVisible($thread, $forum, $followForumRules = true)
    {
        $newState = 'visible';

        $thread = $this->getNormalizedThreadObject($thread);

        if ($thread === null)
        {
            return;
        }

        if ($followForumRules)
        {
            $forum = $this->getNormalizedForumObject($forum);
            if ($forum)
            {
                $newState = $forum->getNewContentState($thread);
            }
        }

        $content = $this->getContent();

        if ($newState === $content['discussion_state'])
        {
            return;
        }

        $content = $this->getNormalizedThreadObject($content);

        $this->setThreadState($content, $newState);
    }

    /**
     * @param $post
     * @param $thread
     * @param $forum
     * @param bool $followForumRules
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function markPostAsVisible(
        /** @noinspection PhpUnusedParameterInspection */
        $post,
        $thread, $forum, $followForumRules = true
    )
    {
        $newState = 'visible';
        if ($followForumRules)
        {
            $forum = $this->getNormalizedForumObject($forum);
            if ($forum)
            {
                $thread = $this->getNormalizedThreadObject($thread);
                $newState = $forum->getNewContentState($thread);
            }
        }

        $content = $this->getContent();

        if ($newState === $content['message_state'])
        {
            return;
        }

        $content = $this->getNormalizedPostObject($content);

        if ($content === null)
        {
            return;
        }

        $this->setPostState($content, $newState);
    }

    /**
     * @return User
     */
    public function getViewingUser()
    {
        return $this->viewingUser;
    }

    /**
     * @param mixed $viewingUser
     */
    public function setViewingUser($viewingUser)
    {
        /** @var User $viewingUser */
        $viewingUser = $this->getNormalizedVisitorObject($viewingUser);

        if (!$viewingUser)
        {
            throw new \RuntimeException('Visitor user could not be detected.');
        }

        $this->viewingUser = $viewingUser;
    }

    /**
     * @param $thread
     * @return null|Thread
     */
    public function getNormalizedThreadObject($thread)
    {
        if ($thread instanceof Thread)
        {
            $threadId = $thread->thread_id;
        }
        else if (is_array($thread) && isset($thread['thread_id']))
        {
            $threadId = $thread['thread_id'];
        }
        else
        {
            return null;
        }

        /** @var Thread $thread */
        $thread = \XF::finder('XF:Thread')->whereId($threadId)->fetchOne();
        return $thread;
    }

    /**
     * @param $post
     * @return null|Post
     */
    public function getNormalizedPostObject($post)
    {
        if ($post instanceof Post)
        {
            $postId = $post->post_id;
        }
        else if (is_array($post) && isset($post['post_id']))
        {
            $postId = $post['post_id'];
        }
        else
        {
            return null;
        }

        /** @var Post $post */
        $post = \XF::finder('XF:Post')->whereId($postId)->fetchOne();
        return $post;
    }

    /**
     * @param $forum
     * @return null|Forum
     */
    public function getNormalizedForumObject($forum)
    {
        if ($forum instanceof Forum)
        {
            return $forum;
        }
        else
        {
            $forum = \XF::finder('XF:Forum')->whereId($forum['node_id'])->fetchOne();
            return $forum;
        }
    }

    public function getNormalizedVisitorObject($visitor)
    {
        if ($visitor instanceof User)
        {
            return $visitor;
        }

        return \XF::finder('XF:User')->whereId($visitor['user_id'])->fetchOne();
    }

    /**
     * @param $type
     * @return null|\XF\InlineMod\AbstractHandler
     * @throws \Exception
     */
    public function getInlineModHandler($type)
    {
        if (!$type)
        {
            return null;
        }

        $class = \XF::app()->getContentTypeFieldValue($type, 'inline_mod_handler_class');
        if (!$class)
        {
            return null;
        }

        $class = \XF::app()->extendClass($class);

        return new $class($type, \XF::app(), \XF::app()->request());
    }
}
