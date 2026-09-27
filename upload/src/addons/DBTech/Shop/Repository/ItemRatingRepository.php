<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Entity\ItemRating;
use DBTech\Shop\Finder\ItemRatingFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Repository;
use XF\Repository\UserAlertRepository;

class ItemRatingRepository extends Repository
{
	/**
	 * @param Item $item
	 * @param array $limits
	 *
	 * @return ItemRatingFinder
	 * @throws \InvalidArgumentException
	 */
	public function findReviewsInItem(Item $item, array $limits = []): ItemRatingFinder
	{
		/** @var ItemRatingFinder $finder */
		$finder = \XF::app()->finder(ItemRatingFinder::class);
		$finder->inItem($item, $limits)
			->where('is_review', 1)
			->setDefaultOrder('rating_date', 'desc');

		return $finder;
	}

	/**
	 * @param array|null $viewableCategoryIds
	 *
	 * @return ItemRatingFinder
	 * @throws \InvalidArgumentException
	 */
	public function findLatestReviews(?array $viewableCategoryIds = null): ItemRatingFinder
	{
		/** @var ItemRatingFinder $finder */
		$finder = \XF::app()->finder(ItemRatingFinder::class)
			->with('Item.Permissions|' . \XF::visitor()->permission_combination_id);

		if (is_array($viewableCategoryIds))
		{
			$finder->where('Item.category_id', $viewableCategoryIds);
		}
		else
		{
			$finder->with('Item.Category.Permissions|' . \XF::visitor()->permission_combination_id);
		}

		$finder->where([
			'Item.item_state' => 'visible',
			'rating_state' => 'visible',
			'is_review' => 1,
		])
			->with('Item', true)
			->with(['Item.Category', 'User'])
			->setDefaultOrder('rating_date', 'desc');

		$cutOffDate = \XF::$time - (\XF::app()->options()->readMarkingDataLifetime * 86400);
		$finder->where('rating_date', '>', $cutOffDate);

		return $finder;
	}

	/**
	 * @param int $itemId
	 * @param int $userId
	 *
	 * @return ItemRating|null
	 * @noinspection PhpIncompatibleReturnTypeInspection
	 */
	public function getCountableRating(int $itemId, int $userId): ?ItemRating
	{
		/** @var ItemRatingFinder $finder */
		$finder = \XF::app()->finder(ItemRatingFinder::class);
		$finder->where([
			'item_id' => $itemId,
			'user_id' => $userId,
			'rating_state' => 'visible',
		])->order('rating_date', 'desc');

		return $finder->fetchOne();
	}

	/**
	 * Returns the ratings that are counted for the the given item user. This should normally return one.
	 * In general, only a bug would have it return more than one but the code is written so that this can be resolved.
	 *
	 * @param int $itemId
	 * @param int $userId
	 *
	 * @return AbstractCollection
	 */
	public function getCountedRatings(int $itemId, int $userId): AbstractCollection
	{
		/** @var ItemRatingFinder $finder */
		$finder = \XF::app()->finder(ItemRatingFinder::class);
		$finder->where([
			'item_id' => $itemId,
			'user_id' => $userId,
			'count_rating' => 1,
		])->order('rating_date', 'desc');

		return $finder->fetch();
	}

	/**
	 * @param ItemRating $rating
	 * @param string $action
	 * @param string $reason
	 * @param array $extra
	 *
	 * @return bool
	 */
	public function sendModeratorActionAlert(
		ItemRating $rating,
		string $action,
		string $reason = '',
		array $extra = []
	): bool
	{
		$item = $rating->Item;

		if (!$item || !$item->user_id || !$item->User)
		{
			return false;
		}

		$extra = array_merge([
			'title' => $item->title,
			'prefix_id' => $item->prefix_id,
			'link' => \XF::app()->router('public')->buildLink('nopath:dbtech-shop/review', $rating),
			'itemLink' => \XF::app()->router('public')->buildLink('nopath:dbtech-shop', $item),
			'reason' => $reason,
			'depends_on_addon_id' => 'DBTech/Shop',
		], $extra);

		$alertRepo = \XF::app()->repository(UserAlertRepository::class);
		$alertRepo->alert(
			$rating->User,
			0,
			'',
			'user',
			$rating->user_id,
			"dbt_shop_rating_$action",
			$extra
		);

		return true;
	}

	/**
	 * @param ItemRating $rating
	 *
	 * @return bool
	 */
	public function sendReviewAlertToItemAuthor(ItemRating $rating): bool
	{
		if (!$rating->isVisible() || !$rating->is_review)
		{
			return false;
		}

		$item = $rating->Item;
		$itemAuthor = $item->User;

		if (!$itemAuthor)
		{
			return false;
		}

		if ($rating->is_anonymous)
		{
			$senderId = 0;
			$senderName = \XF::phrase('anonymous')->render('raw');
		}
		else
		{
			$senderId = $rating->user_id;
			$senderName = $rating->User ? $rating->User->username : \XF::phrase('unknown')->render('raw');
		}

		$alertRepo = \XF::app()->repository(UserAlertRepository::class);
		return $alertRepo->alert(
			$itemAuthor,
			$senderId,
			$senderName,
			'dbtech_shop_rating',
			$rating->item_rating_id,
			'review'
		);
	}
}