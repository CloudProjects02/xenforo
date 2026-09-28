<?php

namespace AddonsLab\ContentHandler\Traits\Context;

use XF\Entity\Thread;
use XF\Mvc\Entity\Entity;

trait ThreadContextTrait
{
	use ForumContextTrait;
	
	protected $thread;
	
	protected static $thread_cache = array();

	/**
	 * @param $threadId
	 * Sets thread array from the database, checking in memory cache first
	 */
	protected function setupThread($threadId)
	{
		if ($this->thread && $this->thread['thread_id'] === $threadId) {
			return;
		}

		$content = $this->getContent();

		if (!empty($content['first_post_id'])) {
			// the content had thread info joined
			$this->thread = $content;
			return;
		}

		if (isset(self::$thread_cache[$threadId])) {
			$this->thread = self::$thread_cache[$threadId];
		} else {
			$this->thread = $this->getThreadById($threadId);
			self::$thread_cache[$threadId] = $this->thread;
		}
	}

	/**
	 * @param mixed $thread
	 */
	public function setThread($thread)
	{
		$this->thread = $thread;
	}

    /**
     * @param $threadId
     * @return null|Thread|Entity
     */
	public function getThreadById($threadId)
    {
        return \XF::finder('XF:Thread')->whereId($threadId)->fetchOne();
    }
}