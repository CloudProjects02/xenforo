<?php

namespace XF\Webhook\Event;

use XF\Webhook\Criteria\Post;

class PostHandler extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return Post::class;
	}

	public function getEntityWith(): array
	{
		return ['Thread', 'Thread.Forum'];
	}
}
