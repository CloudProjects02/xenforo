<?php

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\Finder;

/**
 * Class ItemRating
 *
 * @package DBTech\Shop\Finder
 */
class ItemRating extends Finder
{
	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 * @param array $limits
	 *
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function inItem(\DBTech\Shop\Entity\Item $item, array $limits = []): ItemRating
	{
		$limits = array_replace([
			'visibility' => true
		], $limits);

		$this->where('item_id', $item->item_id);

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksInItem($item);
		}

		return $this;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 *
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function applyVisibilityChecksInItem(\DBTech\Shop\Entity\Item $item): ItemRating
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