<?php

namespace DBTech\Shop\Warning;

use DBTech\Shop\Entity\ItemRating;
use DBTech\Shop\Service\ItemRating\DeleteService;
use XF\Entity\User;
use XF\Entity\Warning;
use XF\Mvc\Entity\Entity;
use XF\Phrase;
use XF\PrintableException;
use XF\Warning\AbstractHandler;

class ItemRatingHandler extends AbstractHandler
{
	/**
	 * @param Entity $entity
	 *
	 * @return string
	 */
	public function getStoredTitle(Entity $entity): string
	{
		/** @var ItemRating $entity */
		return $entity->Item ? $entity->Item->title : '';
	}

	/**
	 * @param $title
	 *
	 * @return Phrase
	 */
	public function getDisplayTitle($title): Phrase
	{
		return \XF::phrase('dbtech_shop_item_review_in_x', ['title' => $title]);
	}

	/**
	 * @param Entity $entity
	 *
	 * @return string
	 */
	public function getContentForConversation(Entity $entity): string
	{
		/** @var ItemRating $entity */
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
		return \XF::app()->router('public')->buildLink(($canonical ? 'canonical:' : '') . 'dbtech-shop/review', $entity);
	}

	/**
	 * @param Entity $entity
	 *
	 * @return User
	 */
	public function getContentUser(Entity $entity): User
	{
		/** @var ItemRating $entity */
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
		/** @var ItemRating $entity */
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
		/** @var ItemRating $entity */
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
		/** @var ItemRating $entity */
		$entity->warning_id = 0;
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
		if ($action == 'delete')
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
	protected function canDeleteContent(Entity $entity): bool
	{
		/** @var ItemRating $entity */
		return $entity->canDelete();
	}

	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();
		return ['User', 'Item', 'Item.Permissions|' . $visitor->permission_combination_id, 'Item.Category', 'Item.Category.Permissions|' . $visitor->permission_combination_id];
	}
}