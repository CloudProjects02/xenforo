<?php

namespace DBTech\Shop\Reaction;

use DBTech\Shop\Entity\TradePost;
use XF\Mvc\Entity\Entity;
use XF\Reaction\AbstractHandler;

class TradePostHandler extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	public function reactionsCounted(Entity $entity): bool
	{
		/** @var TradePost $entity */
		return ($entity->message_state == 'visible');
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['Trade'];
	}
}