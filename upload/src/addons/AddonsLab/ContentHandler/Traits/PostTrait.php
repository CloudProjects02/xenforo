<?php

namespace AddonsLab\ContentHandler\Traits;

use AddonsLab\ContentHandler\Traits\Context\ThreadContextTrait;
use AddonsLab\ContentHandler\Traits\Helper\ModerationTrait;
use XF\Entity\Post;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;

/**
 * Trait PostTrait
 * @package AddonsLab\ContentHandler\Traits
 * @see \AddonsLab\ContentHandler\ContentHandlerInterface
 * Post content type implementation with many helper functions used in indexation type tasks, importers etc.
 */
trait PostTrait
{
    /**
     * Implemented $thread property,
     */
    use ThreadContextTrait;
    use ModerationTrait;

    /**
     * @return array
     */
    public function getThread()
    {
        if (is_null($this->thread))
        {
            $this->assertThreadAndForum();
        }

        return $this->thread;
    }

    /**
     * @return array
     */
    public function getNodeTree()
    {
        return $this->getNodeTreeForList();
    }

    protected function getNodeTreeForList()
    {
        $nodeRepo = $this->getNodeRepo();

        $nodes = $nodeRepo->getNodeList();

        return $nodeRepo->createNodeTree($nodes);

    }

    /**
     * @return \XF\Repository\Node
     */
    protected function getNodeRepo()
    {
        return \XF::app()->repository('XF:Node');
    }

    /**
     * @return array
     */
    public function getForum()
    {
        if (is_null($this->forum))
        {
            $this->assertThreadAndForum();
        }

        return $this->forum;
    }

    /**
     * Makes sure we have thread and forum information available
     * Will not execute any queries if post content was fetched properly, using methods available in this trait
     */
    public function assertThreadAndForum()
    {
        $content = $this->getContent();
        if (!empty($content['thread_id']))
        {
            $this->setupThread($content['thread_id']);
            if ($this->thread)
            {
                $this->setupForum($this->thread['node_id']);
            }
        }
    }

    public function isFirstPost()
    {
        $this->assertThreadAndForum();
        $content = $this->getContent();
        return $content['post_id'] === $this->thread['first_post_id'];
    }

    public function getContentId()
    {
        $content = $this->getContent();
        if ($content === null)
        {
            return null;
        }

        return $content['post_id'];
    }

    /**
     * @return int
     * User ID who created the content
     */
    public function getContentUserId()
    {
        if (!$this->getContentId())
        {
            // the content is not setup
            return 0;
        }

        $content = $this->getContent();

        if (
            (is_array($content) && array_key_exists('user_id', $content))
            || (is_object($content) && property_exists($content, 'user_id'))
        )
        {
            return $content['user_id'];
        }

        // we have to do a query
        $content = $this->getContentById($this->getContentId());

        if (!$content)
        {
            return 0;
        }

        return $content['user_id'];
    }

    /**
     * @return mixed
     */
    public function getContentType()
    {
        return "post";
    }

    public function getContentTypeName()
    {
        return \XF::phrase('posts');
    }

    public function getContentIdsInRange($start, $limit)
    {
        return \XF::finder('XF:Post')
            ->where('post_id', '>', $start)
            ->order('post_id')
            ->pluckFrom('post_id')->fetch($limit)->keys();
    }

    public function getContentUrl()
    {
        return \XF::app()->router('public')->buildLink('canonical:posts', array('post_id' => $this->getContentId()));
    }

    /**
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function markContentAsModerated()
    {
        if ($this->isFirstPost())
        {
            $this->markThreadAsModerated($this->getThread());
        }
        else
        {
            $this->markPostAsModerated($this->getContent());
        }
    }

    /**
     * @throws \XF\PrintableException
     */
    public function deleteContent()
    {
        if ($this->isFirstPost())
        {
            $this->deleteThread($this->thread);
        }
        else
        {
            $this->deletePost($this->getContent());
        }
    }

    /**
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function markContentAsVisible()
    {
        $this->assertThreadAndForum();

        if ($this->isFirstPost())
        {
            $this->markThreadAsVisible($this->getThread(), $this->getForum(), false);
        }
        else
        {
            $this->markPostAsVisible($this->getContent(), $this->getThread(), $this->getForum());
        }

        // reload the content to reflect the changes in visibility
        $this->setContextFromContentId($this->getContentId());
    }

    public function getContentDate()
    {
        $content = $this->getContent();

        return $content['post_date'];
    }

    public function getContentMessage()
    {
        $content = $this->getContent();
        if ($content === null)
        {
            return null;
        }
        return $content['message'];
    }

    public function setContentMessage($message)
    {
        $this->setContentData('message', $message);
    }

    /**
     * @param $contentId
     * @return null|Entity|Post
     */
    public function getContentById($contentId)
    {
        return \XF::finder('XF:Post')->whereId($contentId)->fetchOne();
    }

    protected function _postSetContextFromContentId(
        /** @noinspection PhpUnusedParameterInspection */
        $contentId
    )
    {
        $this->assertThreadAndForum();
    }

    /**
     * @return bool
     */
    public function canModerateContent()
    {
        $content = $this->getNormalizedPostObject($this->getContent());
        if ($content === null)
        {
            return false;
        }

        return $content->canApproveUnapprove();
    }

    public function contentIsVisible()
    {
        if ($this->isFirstPost())
        {
            $thread = $this->getThread();
            return $thread['discussion_state'] === 'visible';
        }

        $content = $this->getContent();
        return $content['message_state'] === 'visible';
    }

    public function setContextFromParentContentId($parentContentType, $parentContentId)
    {
        switch ($parentContentType)
        {
            case 'post':
                $this->setContextFromContentId($parentContentId);
                break;
            case 'thread':
                $this->setupThread($parentContentId);
                if ($this->thread)
                {
                    $this->setupForum($this->thread['node_id']);
                }
                break;
            case 'forum':
                $this->setupForum($parentContentId);
                break;
        }
    }

    /**
     * @param array $contentIds
     * @return \XF\Mvc\Entity\ArrayCollection|Post[]
     */
    public function getContentArrayByIds(array $contentIds)
    {
        $contentIds = array_map('intval', $contentIds);
        return \XF::finder('XF:Post')
            ->with('Thread.Forum', true)
            ->whereIds($contentIds)->fetch();
    }

    public function updateContentMessage(array $contentArray)
    {
        foreach ($contentArray AS $postId => $content)
        {
            $message = $content['message'];
            $content = $this->getNormalizedPostObject($content);

            if ($content === null)
            {
                continue;
            }

            $content->message = $message;
            try
            {
                $content->save();
            }
            catch (PrintableException $e)
            {
            }
            catch (\Exception $e)
            {
            }
        }
    }

    /**
     * @param $contextArray
     * Should have both node and thread info inside
     */
    public function setContextFromArray($contextArray)
    {
        $this->thread = $contextArray;
        $this->forum = $contextArray;
    }
}