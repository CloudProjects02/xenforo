<?php

namespace DBTech\Shop\Warning;

use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\Service\TradePost\DeleterService;
use XF\Entity\User;
use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;
use XF\Phrase;
use XF\PrintableException;
use XF\Warning\AbstractHandler;

class TradePostHandler extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 *
	 * @return string
	 */
	public function getStoredTitle(Entity $entity): string
	{
		/** @var TradePost $entity */
		return $entity->User ? $entity->User->username : '';
	}

	/**
	 * @param $title
	 *
	 * @return Phrase
	 */
	public function getDisplayTitle($title): Phrase
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
		/** @var TradePost $entity */
		return $entity->message;
	}

	/**
	 * @param Entity $entity
	 * @param bool $canonical
	 *
	 * @return string
	 */
	public function getContentUrl(Entity $entity, $canonical = false): string
	{
		/** @var TradePost $entity */
		return \XF::app()->router('public')->buildLink(($canonical ? 'canonical:' : '') . 'dbtech-shop/trade-posts', $entity);
	}

	/**
	 * @param Entity $entity
	 *
	 * @return User
	 */
	public function getContentUser(Entity $entity): User
	{
		/** @var TradePost $entity */
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
		/** @var TradePost $entity */
		return $entity->canView();
	}

	/**
	 * @param Entity $entity
	 * @param Warning $warning
	 *
	 * @throws PrintableException
	 */
	public function onWarning(Entity $entity, Warning $warning): void
	{
		/** @var TradePost $entity */
		$entity->warning_id = $warning->warning_id;
		$entity->save();
	}

	/**
	 * @param Entity $entity
	 * @param Warning $warning
	 *
	 * @throws PrintableException
	 */
	public function onWarningRemoval(Entity $entity, Warning $warning): void
	{
		/** @var TradePost $entity */
		$entity->warning_id = 0;
		$entity->warning_message = '';
		$entity->save();
	}

	/**
	 * @param Entity $entity
	 * @param $action
	 * @param array $options
	 *
	 * @throws PrintableException
	 */
	public function takeContentAction(Entity $entity, $action, array $options): void
	{
		/** @var TradePost $entity */
		if ($action == 'public')
		{
			$message = $options['message'] ?? '';
			if (is_string($message) && strlen($message))
			{
				$entity->warning_message = $message;
				$entity->save();
			}
		}
		else if ($action == 'delete')
		{
			$reason = $options['reason'] ?? '';
			if (!is_string($reason))
			{
				$reason = '';
			}

			$deleter = \XF::app()->service(DeleterService::class, $entity);
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
		/** @var TradePost $entity */
		return true;
	}

	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	protected function canDeleteContent(Entity $entity): bool
	{
		/** @var TradePost $entity */
		return $entity->canDelete();
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['Trade'];
	}
}