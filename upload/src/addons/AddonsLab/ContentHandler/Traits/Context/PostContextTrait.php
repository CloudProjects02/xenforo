<?php

namespace AddonsLab\ContentHandler\Traits\Context;

use XF\Entity\Post;
use XF\Mvc\Entity\Entity;

trait PostContextTrait
{
	protected $post;

	protected static $post_cache = array();

	/**
	 * @param $postId
	 * Sets post array from the database, checking in memory cache first
	 */
	protected function setupPost($postId)
	{
		if ($this->post && $this->post['post_id'] === $postId) {
			return;
		}

		$content = $this->getContent();

		if (!empty($content['first_post_id']) && !empty($content['message'])) {
			// the content had post info joined
			$this->post = $content;
			return;
		}

		if (isset(self::$post_cache[$postId])) {
			$this->post = self::$post_cache[$postId];
		} else {
			$this->post = $this->getPostById($postId);
			self::$post_cache[$postId] = $this->post;
		}
	}

	/**
	 * @param mixed $post
	 */
	public function setPost($post)
	{
		$this->post = $post;
	}

    /**
     * @param $postId
     * @return null|Post|Entity
     */
	public function getPostById($postId)
    {
        return \XF::finder('XF:Post')->whereId($postId)->fetchOne();
    }
}