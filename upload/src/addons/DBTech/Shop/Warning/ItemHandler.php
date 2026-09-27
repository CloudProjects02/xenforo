<?php

namespace DBTech\Shop\Warning;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Service\Item\DeleteService;
use XF\Entity\User;
use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;
use XF\Phrase;
use XF\PrintableException;
use XF\Warning\AbstractHandler;

class ItemHandler extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 *
	 * @return string
	 */
	public function getStoredTitle(Entity $entity): string
	{
		/** @var Item $entity */
		return $entity->Category ? $entity->Category->title : '';
	}

	/**
	 * @param $title
	 *
	 * @return Phrase
	 */
	public function getDisplayTitle($title): Phrase
	{
		return \XF::phrase('dbtech_shop_item_in_x', ['title' => $title]);
	}

	/**
	 * @param Entity $entity
	 *
	 * @return string
	 */
	public function getContentForConversation(Entity $entity): string
	{
		/** @var Item $entity */
		return $entity->description;
	}

	/**
	 * @param Entity $entity
	 * @param bool $canonical
	 *
	 * @return string
	 */
	public function getContentUrl(Entity $entity, $canonical = false): string
	{
		return \XF::app()->router('public')->buildLink(($canonical ? 'canonical:' : '') . 'dbtech-shop', $entity);
	}

	/**
	 * @param Entity $entity
	 *
	 * @return User
	 */
	public function getContentUser(Entity $entity): User
	{
		/** @var Item $entity */
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
		/** @var Item $entity */
		return $entity->canView();
	}

	/**
	 * @param Entity $entity
	 * @param Warning $warning
	 *
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function onWarning(Entity $entity, Warning $warning): void
	{
		/** @var Item $entity */
		$entity->warning_id = $warning->warning_id;
		$entity->save();
	}

	/**
	 * @param Entity $entity
	 * @param Warning $warning
	 *
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function onWarningRemoval(Entity $entity, Warning $warning): void
	{
		/** @var Item $entity */
		$entity->warning_id = 0;
		$entity->warning_message = '';
		$entity->save();
	}

	/**
	 * @param Entity $entity
	 * @param $action
	 * @param array $options
	 *
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function takeContentAction(Entity $entity, $action, array $options): void
	{
		/** @var Item $entity */
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

			$deleter = \XF::app()->service(DeleteService::class, $entity);
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
		return true;
	}

	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	protected function canDeleteContent(Entity $entity): bool
	{
		/** @var Item $entity */
		return $entity->canDelete();
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();
		return ['Permissions|' . $visitor->permission_combination_id, 'Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}
}