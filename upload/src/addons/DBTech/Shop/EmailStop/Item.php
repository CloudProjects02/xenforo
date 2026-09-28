<?php

namespace DBTech\Shop\EmailStop;

use XF\EmailStop\AbstractHandler;

/**
 * Class Item
 *
 * @package DBTech\Shop\EmailStop
 */
class Item extends AbstractHandler
{
	/**
	 * @param \XF\Entity\User $user
	 * @param $contentId
	 *
	 * @return null|\XF\Phrase
	 * @throws \Exception
	 */
	public function getStopOneText(\XF\Entity\User $user, $contentId): ?\XF\Phrase
	{
		/** @var \DBTech\Shop\Entity\Item|null $item */
		$item = \XF::em()->find('DBTech\Shop:Item', $contentId);
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
	 * @param \XF\Entity\User $user
	 *
	 * @return \XF\Phrase
	 */
	public function getStopAllText(\XF\Entity\User $user): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_stop_notification_emails_from_all_items');
	}
	
	/**
	 * @param \XF\Entity\User $user
	 * @param $contentId
	 *
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function stopOne(\XF\Entity\User $user, $contentId)
	{
		/** @var \DBTech\Shop\Entity\Item $item */
		$item = \XF::em()->find('DBTech\Shop:Item', $contentId);
		if ($item)
		{
			/** @var \DBTech\Shop\Repository\ItemWatch $itemWatchRepo */
			$itemWatchRepo = \XF::repository('DBTech\Shop:ItemWatch');
			$itemWatchRepo->setWatchState($item, $user, 'update', ['email_subscribe' => false]);
		}
	}
	
	/**
	 * @param \XF\Entity\User $user
	 *
	 * @throws \InvalidArgumentException
	 */
	public function stopAll(\XF\Entity\User $user)
	{
		/** @var \DBTech\Shop\Repository\ItemWatch $itemWatchRepo */
		$itemWatchRepo = \XF::repository('DBTech\Shop:ItemWatch');
		$itemWatchRepo->setWatchStateForAll($user, 'update', ['email_subscribe' => 0]);

		/** @var \DBTech\Shop\Repository\CategoryWatch $categoryWatchRepo */
		$categoryWatchRepo = \XF::repository('DBTech\Shop:CategoryWatch');
		$categoryWatchRepo->setWatchStateForAll($user, 'update', ['send_email' => 0]);
	}
}