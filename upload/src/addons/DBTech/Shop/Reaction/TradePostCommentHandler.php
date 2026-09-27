<?php

namespace DBTech\Shop\Reaction;

use DBTech\Shop\Entity\TradePostComment;
use XF\Mvc\Entity\Entity;
use XF\Reaction\AbstractHandler;

class TradePostCommentHandler extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	public function reactionsCounted(Entity $entity): bool
	{
		/** @var TradePostComment $entity */
		return ($entity->message_state == 'visible');
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['TradePost', 'TradePost.Trade'];
	}
}