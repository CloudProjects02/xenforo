<?php

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\Finder;

/**
 * Class Purchase
 * @package DBTech\Shop\Finder
 */
class Purchase extends Finder
{
	/**
	 * @param bool $allowOwnPending
	 *
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function applyGlobalVisibilityChecks(bool $allowOwnPending = false): Purchase
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		$conditions = [];

		$viewableStates = ['visible'];
		
		if ($visitor->hasPermission('dbtech_shop', 'viewDeleted'))
		{
			$viewableStates[] = 'deleted';
			
			$this->with('Item.DeletionLog');
		}
		
		if ($visitor->hasPermission('dbtech_shop', 'viewModerated'))
		{
			$viewableStates[] = 'moderated';
		}
		elseif ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'Item.item_state' => 'moderated',
				'Item.user_id' => $visitor->user_id
			];
		}
		
		$conditions[] = ['item_state', $viewableStates];
		
		$this->whereOr($conditions);
		
		return $this;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Category $category
	 * @param bool $allowOwnPending
	 *
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function applyVisibilityChecksInCategory(
		\DBTech\Shop\Entity\Category $category,
		bool $allowOwnPending = false
	): Purchase {
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		$conditions = [];

		$viewableStates = ['visible'];
		
		if ($category->canViewDeletedItems())
		{
			$viewableStates[] = 'deleted';
			
			$this->with('Item.DeletionLog');
		}
		
		if ($category->canViewModeratedItems())
		{
			$viewableStates[] = 'moderated';
		}
		elseif ($visitor->user_id && $allowOwnPending)
		{
			$conditions[] = [
				'Item.item_state' => 'moderated',
				'Item.user_id' => $visitor->user_id
			];
		}
		
		$conditions[] = ['Item.item_state', $viewableStates];
		
		$this->whereOr($conditions);
		
		return $this;
	}
	
	/**
	 * @param null $userId
	 *
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function watchedOnly($userId = null): Purchase
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		if (!$userId)
		{
			// no user, just ignore
			return $this;
		}
		
		$this->whereOr(
			['Item.Watch|' . $userId . '.user_id', '!=', null],
			['Item.Category.Watch|' . $userId . '.user_id', '!=', null]
		);
		
		return $this;
	}
	
	/**
	 * @param string $match
	 * @param bool $caseSensitive
	 * @param bool $prefixMatch
	 * @param bool $exactMatch
	 *
	 * @return $this
	 */
	public function searchText(
		string $match,
		bool $caseSensitive = false,
		bool $prefixMatch = false,
		bool $exactMatch = false
	): Purchase {
		if ($match)
		{
//			$expression = 'MasterTitle.phrase_text';
			$expression = 'Item.title';
			if ($caseSensitive)
			{
				$expression = $this->expression('BINARY %s', $expression);
			}

			if ($exactMatch)
			{
				$this->where($expression, $match);
			}
			else
			{
				$this->where($expression, 'LIKE', $this->escapeLike($match, $prefixMatch ? '?%' : '%?%'));
			}
		}

		return $this;
	}
	
	/**
	 * @return $this
	 */
	public function orderForList(): Purchase
	{
		$this->order('display_order', 'DESC');
		
		$this->orderTitle();
		
		return $this;
	}
	
	/**
	 * @param string $direction
	 * @return $this
	 */
	public function orderTitle(string $direction = 'ASC'): Purchase
	{
//		$expression = $this->columnUtf8('MasterTitle.phrase_text');
		$expression = $this->columnUtf8('title');
		$this->order($expression, $direction);

		return $this;
	}
	
	/**
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function useDefaultOrder(): Purchase
	{
		$defaultOrder = $this->app()->options()->dbtechShopListDefaultOrder ?: 'last_update';
		$defaultDir = in_array($defaultOrder, ['title', 'display_order']) ? 'asc' : 'desc';
		
		$this->setDefaultOrder($defaultOrder, $defaultDir);
		
		return $this;
	}
}