<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Shop\Finder;

use DBTech\Shop\Entity\Item;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Shop\Entity\ItemRating> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Shop\Entity\ItemRating> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Shop\Entity\ItemRating|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Shop\Entity\ItemRating>
 */
class ItemRatingFinder extends Finder
{
	/**
	 * @param Item $item
	 * @param array $limits
	 *
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function inItem(Item $item, array $limits = []): ItemRatingFinder
	{
		$limits = array_replace([
			'visibility' => true,
		], $limits);

		$this->where('item_id', $item->item_id);

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksInItem($item);
		}

		return $this;
	}

	/**
	 * @param Item $item
	 *
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function applyVisibilityChecksInItem(Item $item): ItemRatingFinder
	{
		$conditions = [];
		$viewableStates = ['visible'];

		if ($item->canViewDeletedContent())
		{
			$viewableStates[] = 'deleted';

			$this->with('DeletionLog');
		}

		$conditions[] = ['rating_state', $viewableStates];

		$this->whereOr($conditions);

		return $this;
	}
}