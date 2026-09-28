<?php

namespace AddonsLab\ContentHandler\Traits\Context;

use XF\Entity\Forum;

trait ForumContextTrait
{
	protected $forum;
	protected static $forum_cache = array();

	/**
	 * @param $forumId
	 * Sets forum array from the database, checking in memory cache first
	 */
	protected function setupForum($forumId)
	{
		if ($this->forum && $this->forum['node_id'] === $forumId) {
			return;
		}

		$content = $this->getContent();

		if (!empty($content['node_type_id'])) {
			// the content had forum info joined
			$this->forum = $content;
			return;
		}

		if (isset(self::$forum_cache[$forumId])) {
			$this->forum = self::$forum_cache[$forumId];
		} else {
			$this->forum = $this->getForumById($forumId);
			self::$forum_cache[$forumId] = $this->forum;
		}
	}
    
	/**
	 * @param mixed $forum
	 */
	public function setForum($forum)
	{
		$this->forum = $forum;
	}

    /**
     * @param $forumId
     * @return null|Forum
     */
	public function getForumById($forumId)
    {
        return \XF::finder('XF:Forum')->whereId($forumId)->fetchOne();
    }
}