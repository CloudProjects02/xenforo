<?php

namespace DBTech\Shop\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Class ItemRating
 *
 * @package DBTech\Shop\Repository
 */
class ItemRating extends Repository
{
	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 * @param array $limits
	 *
	 * @return \DBTech\Shop\Finder\ItemRating
	 * @throws \InvalidArgumentException
	 */
	public function findReviewsInItem(\DBTech\Shop\Entity\Item $item, array $limits = []): \DBTech\Shop\Finder\ItemRating
	{
		/** @var \DBTech\Shop\Finder\ItemRating $finder */
		$finder = $this->finder('DBTech\Shop:ItemRating');
		$finder->inItem($item, $limits)
			->where('is_review', 1)
			->setDefaultOrder('rating_date', 'desc');

		return $finder;
	}
	
	/**
	 * @param array|null $viewableCategoryIds
	 *
	 * @return \DBTech\Shop\Finder\ItemRating
	 * @throws \InvalidArgumentException
	 */
	public function findLatestReviews(array $viewableCategoryIds = null): \DBTech\Shop\Finder\ItemRating
	{
		/** @var \DBTech\Shop\Finder\ItemRating $finder */
		$finder = $this->finder('DBTech\Shop:ItemRating')
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
				'is_review' => 1
			])
			->with('Item', true)
			->with(['Item.Category', 'User'])
			->setDefaultOrder('rating_date', 'desc');

		$cutOffDate = \XF::$time - ($this->options()->readMarkingDataLifetime * 86400);
		$finder->where('rating_date', '>', $cutOffDate);

		return $finder;
	}
	
	/**
	 * @param int $itemId
	 * @param int $userId
	 *
	 * @return \DBTech\Shop\Entity\ItemRating|\XF\Mvc\Entity\Entity|null
	 */
	public function getCountableRating(int $itemId, int $userId)
	{
		/** @var \DBTech\Shop\Finder\ItemRating $finder */
		$finder = $this->finder('DBTech\Shop:ItemRating');
		$finder->where([
			'item_id' => $itemId,
			'user_id' => $userId,
			'rating_state' => 'visible'
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
	 * @return \XF\Mvc\Entity\AbstractCollection
	 */
	public function getCountedRatings(int $itemId, int $userId): \XF\Mvc\Entity\AbstractCollection
	{
		/** @var \DBTech\Shop\Finder\ItemRating $finder */
		$finder = $this->finder('DBTech\Shop:ItemRating');
		$finder->where([
			'item_id' => $itemId,
			'user_id' => $userId,
			'count_rating' => 1
		])->order('rating_date', 'desc');

		return $finder->fetch();
	}
	
	/**
	 * @param \DBTech\Shop\Entity\ItemRating $rating
	 * @param string $action
	 * @param string $reason
	 * @param array $extra
	 *
	 * @return bool
	 */
	public function sendModeratorActionAlert(
		\DBTech\Shop\Entity\ItemRating $rating,
		string $action,
		string $reason = '',
		array $extra = []
	): bool {
		$item = $rating->Item;

		if (!$item || !$item->user_id || !$item->User)
		{
			return false;
		}

		$extra = array_merge([
			'title' => $item->title,
			'prefix_id' => $item->prefix_id,
			'link' => $this->app()->router('public')->buildLink('nopath:dbtech-shop/review', $rating),
			'itemLink' => $this->app()->router('public')->buildLink('nopath:dbtech-shop', $item),
			'reason' => $reason,
			'depends_on_addon_id' => 'DBTech/Shop',
		], $extra);

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$rating->User,
			0,
			'',
			'user',
			$rating->user_id,
			"dbt_shop_rating_{$action}",
			$extra
		);

		return true;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\ItemRating $rating
	 *
	 * @return bool
	 */
	public function sendReviewAlertToItemAuthor(\DBTech\Shop\Entity\ItemRating $rating): bool
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

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
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