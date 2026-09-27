<?php

namespace DBTech\Shop\EmailStop;

use DBTech\Shop\Entity\Category;
use DBTech\Shop\Repository\CategoryWatchRepository;
use DBTech\Shop\Repository\ItemWatchRepository;
use XF\EmailStop\AbstractHandler;
use XF\Entity\User;
use XF\Phrase;
use XF\PrintableException;

class CategoryHandler extends AbstractHandler
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
		$category = \XF::app()->em()->find(Category::class, $contentId);
		$canView = \XF::asVisitor(
			$user,
			function () use ($category): bool { return $category && $category->canView(); }
		);

		if ($canView)
		{
			return \XF::phrase('stop_notification_emails_from_x', ['title' => $category->title]);
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
		return \XF::phrase('stop_notification_emails_from_all_categories');
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
		$category = \XF::app()->em()->find(Category::class, $contentId);
		if ($category)
		{
			$categoryWatchRepo = \XF::app()->repository(CategoryWatchRepository::class);
			$categoryWatchRepo->setWatchState($category, $user, 'update', ['email_subscribe' => 0]);
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