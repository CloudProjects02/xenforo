<?php

namespace DBTech\Shop\Reaction;

use XF\Reaction\AbstractHandler;
use XF\Mvc\Entity\Entity;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\Reaction
 */
class TradePost extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	public function reactionsCounted(Entity $entity): bool
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
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