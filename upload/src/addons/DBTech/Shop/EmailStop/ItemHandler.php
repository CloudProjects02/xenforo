<?php

namespace DBTech\Shop\EmailStop;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Repository\CategoryWatchRepository;
use DBTech\Shop\Repository\ItemWatchRepository;
use XF\EmailStop\AbstractHandler;
use XF\Entity\User;
use XF\Phrase;
use XF\PrintableException;

class ItemHandler extends AbstractHandler
{
	/**
	 * @param User $user
	 * @param $contentId
	 *
	 * @return null|Phrase
	 * @throws \Exception
	 */
	public function getStopOneText(User $user, $contentId): ?Phrase
	{
		$item = \XF::app()->em()->find(Item::class, $contentId);
		$canView = \XF::asVisitor(
			$user,
			function () use ($item): bool { return $item && $item->canView(); }
		);

		if ($canView)
		{
			return \XF::phrase('stop_notification_emails_from_x', ['title' => $item->title]);
		}

		return null;
	}

	/**
	 * @param User $user
	 *
	 * @return Phrase
	 */
	public function getStopAllText(User $user): Phrase
	{
		return \XF::phrase('dbtech_shop_stop_notification_emails_from_all_items');
	}

	/**
	 * @param User $user
	 * @param $contentId
	 *
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function stopOne(User $user, $contentId): void
	{
		$item = \XF::app()->em()->find(Item::class, $contentId);
		if ($item)
		{
			$itemWatchRepo = \XF::app()->repository(ItemWatchRepository::class);
			$itemWatchRepo->setWatchState($item, $user, 'update', ['email_subscribe' => 0]);
		}
	}

	/**
	 * @param User $user
	 *
	 * @throws \InvalidArgumentException
	 */
	public function stopAll(User $user): void
	{
		$itemWatchRepo = \XF::app()->repository(ItemWatchRepository::class);
		$itemWatchRepo->setWatchStateForAll($user, 'update', ['email_subscribe' => 0]);

		$categoryWatchRepo = \XF::app()->repository(CategoryWatchRepository::class);
		$categoryWatchRepo->setWatchStateForAll($user, 'update', ['send_email' => 0]);
	}
}