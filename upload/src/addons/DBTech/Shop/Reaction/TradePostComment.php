<?php

namespace DBTech\Shop\Reaction;

use XF\Reaction\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class TradePostComment
 *
 * @package DBTech\Shop\Reaction
 */
class TradePostComment extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	public function reactionsCounted(Entity $entity): bool
	{
		/** @var \DBTech\Shop\Entity\TradePostComment $entity */
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