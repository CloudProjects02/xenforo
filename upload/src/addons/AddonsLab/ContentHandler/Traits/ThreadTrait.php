<?php

namespace AddonsLab\ContentHandler\Traits;

use AddonsLab\ContentHandler\Traits\Context\ForumContextTrait;
use AddonsLab\ContentHandler\Traits\Context\PostContextTrait;
use AddonsLab\ContentHandler\Traits\Helper\ModerationTrait;

/**
 * Trait ThreadTrait
 * @package AddonsLab\ContentHandler\Traits
 * @see \AddonsLab\ContentHandler\ContentHandlerInterface
 * Post content type implementation with many helper functions used in indexation type tasks, importers etc.
 */
trait ThreadTrait
{
    use ForumContextTrait;
    use PostContextTrait;
    use ModerationTrait;

    /**
     * @return mixed
     */
    public function getPost()
    {
        if (is_null($this->post))
        {
            $this->assertPostAndForum();
        }

        return $this->post;
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
    public function assertPostAndForum()
    {
        $content = $this->getContent();

        if (!empty($content['node_id']))
        {
            $this->setupForum($content['node_id']);
        }

        if (!empty($content['first_post_id']))
        {
            $this->setupPost($content['first_post_id']);
        }
    }

    public function getContentId()
    {
        $content = $this->getContent();
        return $content['thread_id'];
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
        return "thread";
    }

    public function getContentTypeName()
    {
        return \XF::phrase('threads');
    }

    public function getContentIdsInRange($start, $limit)
    {
        return \XF::finder('XF:Thread')
            ->where('thread_id', '>', $start)
            ->order('thread_id')
            ->pluckFrom('thread_id')->fetch($limit)->keys();
    }

    public function getContentUrl()
    {
        return \XF::app()->router('public')->buildLink('canonical:threads', array('thread_id' => $this->getContentId()));
    }

    /**
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function markContentAsModerated()
    {
        $this->markThreadAsModerated($this->getContent());
    }

    /**
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function markContentAsVisible()
    {
        $this->assertPostAndForum();

        $this->markThreadAsVisible($this->getContent(), $this->getForum(), false);

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
        return $content['message'];
    }

    public function setContentMessage($message)
    {
        $this->setContentData('message', $message);
    }

    public function getContentById($contentId)
    {
        return \XF::finder('XF:Thread')->whereId($contentId)->fetchOne();
    }

    protected function _postSetContextFromContentId(
        /** @noinspection PhpUnusedParameterInspection */
        $contentId
    )
    {
        $this->assertPostAndForum();
    }

    public function canModerateContent()
    {
        $thread = $this->getNormalizedThreadObject($this->getContent());
        return $thread && $thread->canApproveUnapprove();
    }

    public function contentIsVisible()
    {
        $content = $this->getContent();
        return $content['discussion_state'] === 'visible';
    }

    public function setContextFromParentContentId($parentContentType, $parentContentId)
    {
        switch ($parentContentType)
        {
            case 'thread':
                $this->setContextFromContentId($parentContentId);
                break;
            case 'forum':
                $this->setupForum($parentContentId);
                break;
        }
    }

    /**
     * @param array $contentIds
     * @return mixed
     */
    public function getContentArrayByIds(array $contentIds)
    {
        $contentIds = array_map('intval', $contentIds);
        return \XF::finder('XF:Thread')->whereIds($contentIds)->fetch();
    }

    /**
     * @param array $contentArray
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function updateContentMessage(array $contentArray)
    {
        foreach ($contentArray AS $postId => $content)
        {
            $message = $content['message'];
            $thread = $this->getNormalizedThreadObject($content);

            if ($thread === null)
            {
                continue;
            }
            $thread->FirstPost->message = $message;
            $thread->save();
        }
    }

    /**
     * @throws \XF\PrintableException
     */
    public function deleteContent()
    {
        $this->deleteThread($this->getContent());
    }

    /**
     * @param $contextArray
     * Should have both node and thread info inside
     */
    public function setContextFromArray($contextArray)
    {
        $this->forum = $contextArray;
    }
}