<?php

namespace DBTech\Shop\Warning;

use XF\Warning\AbstractHandler;
use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\Warning
 */
class TradePost extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 *
	 * @return string
	 */
	public function getStoredTitle(Entity $entity): string
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
		return $entity->User ? $entity->User->username : '';
	}
	
	/**
	 * @param $title
	 *
	 * @return \XF\Phrase
	 */
	public function getDisplayTitle($title): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_trade_post_by_x', ['name' => $title]);
	}
	
	/**
	 * @param Entity $entity
	 *
	 * @return string
	 */
	public function getContentForConversation(Entity $entity): string
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
		return $entity->message;
	}
	
	/**
	 * @param Entity $entity
	 * @param bool $canonical
	 *
	 * @return mixed|string
	 */
	public function getContentUrl(Entity $entity, $canonical = false): string
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
		return \XF::app()->router('public')->buildLink(($canonical ? 'canonical:' : '') . 'dbtech-shop/trade-posts', $entity);
	}
	
	/**
	 * @param Entity $entity
	 *
	 * @return \XF\Entity\User
	 */
	public function getContentUser(Entity $entity): \XF\Entity\User
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
		return $entity->User;
	}
	
	/**
	 * @param Entity $entity
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canViewContent(Entity $entity, &$error = null): bool
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
		return $entity->canView();
	}
	
	/**
	 * @param Entity $entity
	 * @param Warning $warning
	 *
	 * @throws \XF\PrintableException
	 */
	public function onWarning(Entity $entity, Warning $warning): void
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
		$entity->warning_id = $warning->warning_id;
		$entity->save();
	}
	
	/**
	 * @param Entity $entity
	 * @param Warning $warning
	 *
	 * @throws \XF\PrintableException
	 */
	public function onWarningRemoval(Entity $entity, Warning $warning): void
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
		$entity->warning_id = 0;
		$entity->warning_message = '';
		$entity->save();
	}
	
	/**
	 * @param Entity $entity
	 * @param $action
	 * @param array $options
	 *
	 * @throws \XF\PrintableException
	 */
	public function takeContentAction(Entity $entity, $action, array $options): void
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
		if ($action == 'public')
		{
			$message = isset($options['message']) ? $options['message'] : '';
			if (is_string($message) && strlen($message))
			{
				$entity->warning_message = $message;
				$entity->save();
			}
		}
		elseif ($action == 'delete')
		{
			$reason = isset($options['reason']) ? $options['reason'] : '';
			if (!is_string($reason))
			{
				$reason = '';
			}

			/** @var \DBTech\Shop\Service\TradePost\Deleter $deleter */
			$deleter = \XF::app()->service('DBTech\Shop:TradePost\Deleter', $entity);
			$deleter->delete('soft', $reason);
		}
	}
	
	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	protected function canWarnPublicly(Entity $entity): bool
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
		return true;
	}
	
	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	protected function canDeleteContent(Entity $entity): bool
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
		return $entity->canDelete('soft');
	}
	
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['Trade'];
	}
}