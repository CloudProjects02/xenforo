<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\ItemType\AbstractHandler;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

/**
 * Class Item
 * @package DBTech\Shop\Repository
 */
class Item extends Repository
{
	/** @var array|null */
	protected $handlers;
	
	/**
	 * @param array|null $viewableCategoryIds
	 * @param array $limits
	 *
	 * @return \DBTech\Shop\Finder\Item
	 * @throws \InvalidArgumentException
	 */
	public function findItemsForOverviewList(array $viewableCategoryIds = null, array $limits = []): \DBTech\Shop\Finder\Item
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => false
		], $limits);
		
		/** @var \DBTech\Shop\Finder\Item $itemFinder */
		$itemFinder = $this->finder('DBTech\Shop:Item')
			->with('Permissions|' . \XF::visitor()->permission_combination_id)
			->where('display_in_list', true);
		
		if (is_array($viewableCategoryIds))
		{
			$itemFinder->where('category_id', $viewableCategoryIds);
		}
		else
		{
			$itemFinder->with('Category.Permissions|' . \XF::visitor()->permission_combination_id);
		}
		
		$itemFinder
			->with('fullCategory')
			->useDefaultOrder();
		
		if ($limits['visibility'])
		{
			$itemFinder->applyGlobalVisibilityChecks($limits['allowOwnPending']);
		}
		
		return $itemFinder;
	}
	
	/**
	 * @param array|null $viewableCategoryIds
	 *
	 * @return \DBTech\Shop\Finder\Item
	 * @throws \InvalidArgumentException
	 */
	public function findNewItems(array $viewableCategoryIds = null): \DBTech\Shop\Finder\Item
	{
		/** @var \DBTech\Shop\Finder\Item $itemFinder */
		$itemFinder = $this->finder('DBTech\Shop:Item');
		
		if (is_array($viewableCategoryIds))
		{
			$itemFinder->where('category_id', $viewableCategoryIds);
		}
		else
		{
			$itemFinder->with('Category.Permissions|' . \XF::visitor()->permission_combination_id);
		}
		
		$itemFinder
			->where('display_in_list', true)
			->where('item_state', 'visible')
			->with('User')
			->with('Permissions|' . \XF::visitor()->permission_combination_id)
			->order('last_update', 'desc');
		
		return $itemFinder;
	}
	
	/**
	 * @param array|null $viewableCategoryIds
	 *
	 * @return \DBTech\Shop\Finder\Item
	 * @throws \InvalidArgumentException
	 */
	public function findTopItems(array $viewableCategoryIds = null): \DBTech\Shop\Finder\Item
	{
		/** @var \DBTech\Shop\Finder\Item $itemFinder */
		$itemFinder = $this->finder('DBTech\Shop:Item');
		
		if (is_array($viewableCategoryIds))
		{
			$itemFinder->where('category_id', $viewableCategoryIds);
		}
		else
		{
			$itemFinder->with('Category.Permissions|' . \XF::visitor()->permission_combination_id);
		}
		
		$itemFinder
			->where('display_in_list', true)
			->where('rating_count', '>', 0)
			->where('item_state', 'visible')
			->with(['User', 'Permissions|' . \XF::visitor()->permission_combination_id])
			->setDefaultOrder('rating_weighted', 'desc');
		
		return $itemFinder;
	}
	
	/**
	 * @param null $userId
	 *
	 * @return \DBTech\Shop\Finder\Item
	 * @throws \InvalidArgumentException
	 */
	public function findItemsForWatchedList($userId = null): \DBTech\Shop\Finder\Item
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		$userId = (int)$userId;
		
		/** @var \DBTech\Shop\Finder\Item $finder */
		$finder = $this->finder('DBTech\Shop:Item');
		
		$finder
			->with('fullCategory')
			->with('Watch|' . $userId, true)
			->where('item_state', 'visible')
			->setDefaultOrder('last_update', 'DESC');
		
		return $finder;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Item $thisItem
	 *
	 * @return \DBTech\Shop\Finder\Item
	 * @throws \InvalidArgumentException
	 */
	public function findOtherItemsByAuthor(\DBTech\Shop\Entity\Item $thisItem): \DBTech\Shop\Finder\Item
	{
		/** @var \DBTech\Shop\Finder\Item $itemFinder */
		$itemFinder = $this->finder('DBTech\Shop:Item');
		
		$itemFinder
			->with(['User', 'Permissions|' . \XF::visitor()->permission_combination_id, 'Category', 'Category.Permissions|' . \XF::visitor()->permission_combination_id])
			->where('item_state', 'visible')
			->where('user_id', $thisItem->user_id)
			->where('item_id', '<>', $thisItem->item_id)
			->setDefaultOrder('last_update', 'desc');
		
		return $itemFinder;
	}
	
	/**
	 * @param int $userId
	 * @param array|null $viewableCategoryIds
	 * @param array $limits
	 *
	 * @return \DBTech\Shop\Finder\Item
	 * @throws \InvalidArgumentException
	 */
	public function findItemsByUser(int $userId, array $viewableCategoryIds = null, array $limits = []): \DBTech\Shop\Finder\Item
	{
		/** @var \DBTech\Shop\Finder\Item $itemFinder */
		$itemFinder = $this->finder('DBTech\Shop:Item');
		
		$itemFinder->where('user_id', $userId)
			->with('Permissions|' . \XF::visitor()->permission_combination_id)
			->with('full|category')
			->setDefaultOrder('last_update', 'desc');
		
		if (is_array($viewableCategoryIds))
		{
			// if we have viewable category IDs, we likely have those permissions
			$itemFinder->where('category_id', $viewableCategoryIds);
		}
		else
		{
			$itemFinder->with('Category.Permissions|' . \XF::visitor()->permission_combination_id);
		}
		
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => $userId == \XF::visitor()->user_id
		], $limits);
		
		if ($limits['visibility'])
		{
			$itemFinder->applyGlobalVisibilityChecks($limits['allowOwnPending']);
		}
		
		return $itemFinder;
	}
	
	/**
	 * @return array
	 */
	public function getCacheData(): array
	{
		$cache = [];
		
		/** @var \DBTech\Shop\Entity\Item[] $entities */
		$entities = $this->finder('DBTech\Shop:Item')->fetch();
		foreach ($entities as $entity)
		{
			$cache[$entity->getIdentifier()] = $entity->toArray(false);
		}
		
		return $cache;
	}
	
	/**
	 * @return array
	 */
	public function rebuildCache(): array
	{
		$cache = $this->getCacheData();
		\XF::registry()->set('dbtShopItems', $cache);
		return $cache;
	}
	
	/**
	 * @return \DBTech\Shop\ItemType\AbstractHandler[]
	 * @throws \Exception
	 */
	public function getHandlers(): ?array
	{
		if (!$this->handlers)
		{
			$handlers = [];
			
			foreach (\XF::app()->getContentTypeField('dbtech_shop_itemtype_handler_class') AS $contentType => $handlerClass)
			{
				if (class_exists($handlerClass))
				{
					$handlerClass = \XF::extendClass($handlerClass);
					$handlers[$contentType] = new $handlerClass($contentType);
				}
			}
			
			$this->handlers = $handlers;
		}
		
		return $this->handlers;
	}
	
	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return \DBTech\Shop\ItemType\AbstractHandler|null
	 * @throws \Exception
	 */
	public function getHandler(string $type, bool $throw = false): ?AbstractHandler
	{
		$handlers = $this->getHandlers();
		if (!array_key_exists($type, $handlers))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No item type handler for '$type'");
			}
			return null;
		}

		return clone $handlers[$type];
		
		/*
		$handlerClass = \XF::app()->getContentTypeFieldValue($type, 'dbtech_shop_itemtype_handler_class');
		if (!$handlerClass)
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("No item type handler for '$type'");
			}
			return null;
		}

		if (!class_exists($handlerClass))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Item type handler for '$type' does not exist: $handlerClass");
			}
			return null;
		}

		$handlerClass = \XF::extendClass($handlerClass);
		return new $handlerClass($type);
		*/
	}
	
	/**
	 * @param bool $onlyActive
	 * @param bool $onlyWithItems
	 *
	 * @return ArrayCollection
	 * @throws \Exception
	 */
	public function getItemTypes(bool $onlyActive = false, bool $onlyWithItems = false): ?ArrayCollection
	{
		$itemTypes = new ArrayCollection($this->getHandlers());
		
		if ($onlyActive)
		{
			$itemTypes = $itemTypes->filter(function (AbstractHandler $itemType): ?AbstractHandler
			{
				if (!$itemType->isActive())
				{
					return null;
				}
				
				return $itemType;
			});
		}
		
		if ($onlyWithItems)
		{
			$visitor = \XF::visitor();
			
			/** @var \DBTech\Shop\Entity\Item[]|ArrayCollection $items */
			$items = $this->finder('DBTech\Shop:Item')
				->with([
					'Permissions|' . $visitor->permission_combination_id,
					'Category',
					'Category.Permissions|' . $visitor->permission_combination_id
				])
				->fetch()
			;
			
			$itemTypes = $itemTypes->filter(function (AbstractHandler $itemType) use ($items, $onlyActive): ?AbstractHandler
			{
				if ($onlyActive)
				{
					$items = $items->filterViewable();
				}
				
				$activeItems = $items->filter(function (\DBTech\Shop\Entity\Item $item) use ($itemType): ?\DBTech\Shop\Entity\Item
				{
					if ($item->item_type_id == $itemType->getContentType())
					{
						return $item;
					}
					
					return null;
				});
				
				if (!$activeItems->count())
				{
					return null;
				}
				
				return $itemType;
			});
		}
		
		return $itemTypes;
	}
	
	/**
	 * @return Finder
	 */
	public function findItemsForList(): Finder
	{
		return $this->finder('DBTech\Shop:Item')->order([['display_order', 'ASC'], ['title', 'ASC']]);
	}
	
	/**
	 * @return \DBTech\Shop\Finder\Item|\XF\Mvc\Entity\Finder
	 */
	public function findEntriesForPermissionList()
	{
		return $this->findItemsForList();
	}
	
	/**
	 * @param bool $onlyActive
	 * @param bool $forFullView
	 *
	 * @return array
	 */
	public function getItemTitlePairs(bool $onlyActive = false, bool $forFullView = false): array
	{
		$itemFinder = $this->findItemsForList();
		/*
		if ($forFullView)
		{
			$itemFinder->with('Currency');
		}
		*/
		
		$items = $itemFinder->fetch();
		if ($onlyActive)
		{
			$items = $items->filterViewable();
		}
		
		$arr = $items->pluck(function (\DBTech\Shop\Entity\Item $e, int $k) use ($forFullView): array
		{
			$title = $e->getTitle();
			if ($title instanceof \XF\Phrase)
			{
				$title = $title->render();
			}

			/*
			if ($forFullView)
			{
				$title .= ' (' . $e->Currency->title . ')';
			}
			*/
			
			return [$k, $title];
		}, false);
		
		asort($arr);
		
		return $arr;
	}

	/**
	 * @param bool $filterActive
	 * @param bool $onlyWithItems
	 * @param \XF\Mvc\Entity\ArrayCollection|null $itemTypes
	 *
	 * @return array|\XF\Mvc\Entity\ArrayCollection
	 * @throws \Exception
	 */
	public function getItemTypeTitlePairs(
		bool $filterActive = false,
		bool $onlyWithItems = false,
		?ArrayCollection $itemTypes = null
	) {
		$itemTypes = $itemTypes ?: $this->getItemTypes($filterActive, $onlyWithItems);

		$arr = $itemTypes->pluck(function (AbstractHandler $e, $k): array
		{
			return [$k, $e->getTitle()->render()];
		}, false);
		
		asort($arr);
		
		return $arr;
	}
	
	/**
	 * @param \XF\Mvc\Entity\ArrayCollection|null $entries
	 * @param int $rootId
	 * @return \XF\Tree
	 */
	public function createItemTree(?ArrayCollection $entries = null, int $rootId = 0): \XF\Tree
	{
		if ($entries === null)
		{
			$entries = $this->findItemsForList()->fetch();
		}
		
		return new \XF\Tree($entries, 'parent_item_id', $rootId);
	}

	/**
	 * @param int $userId
	 *
	 * @return int
	 */
	public function getUserItemCount(int $userId): int
	{
		return (int)$this->db()->fetchOne("
			SELECT COUNT(item_id)
			FROM xf_dbtech_shop_item
			WHERE user_id = ?
				AND item_state = 'visible'
		", $userId);
	}
	
	/**
	 * @param \XF\Mvc\Entity\AbstractCollection |null $itemTree
	 * @param array|null $flattenedCategoryTree
	 *
	 * @return array
	 */
	public function getItemsByCategory(?AbstractCollection $itemTree = null, ?array $flattenedCategoryTree = null): array
	{
		$itemTree = $itemTree ?: $this->findItemsForList()->fetch();
		$flattenedCategoryTree = $flattenedCategoryTree ?: $this->repository('DBTech\Shop:Category')->createCategoryTree()->getFlattened(0);
		
		$itemsByCategory = [];
		foreach ($flattenedCategoryTree as $treeEntry)
		{
			foreach ($itemTree as $record)
			{
				if ($treeEntry['record']->category_id != $record->category_id)
				{
					continue;
				}
				
				$itemsByCategory[$record->category_id][] = [
					'record' => $record,
					'depth'  => 0
				];
			}
		}
		
		
		return [
			'categories' => $flattenedCategoryTree,
			'items' => $itemsByCategory
		];
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 * @param string $action
	 * @param string $reason
	 * @param array $extra
	 * @param \XF\Entity\User|null $forceUser
	 *
	 * @return bool
	 */
	public function sendModeratorActionAlert(
		\DBTech\Shop\Entity\Item $item,
		string $action,
		string $reason = '',
		array $extra = [],
		?\XF\Entity\User $forceUser = null
	): bool {
		if (!$forceUser)
		{
			if (!$item->user_id || !$item->User)
			{
				return false;
			}
			
			$forceUser = $item->User;
		}
		
		$extra = array_merge([
			'title' => $item->title,
			'prefix_id' => $item->prefix_id,
			'link' => $this->app()->router('public')->buildLink('nopath:dbtech-shop', $item),
			'reason' => $reason,
			'depends_on_addon_id' => 'DBTech/Shop',
		], $extra);
		
		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$forceUser,
			0,
			'',
			'user',
			$item->user_id,
			"dbt_shop_item_{$action}",
			$extra
		);
		
		return true;
	}
	
	/**
	 * @throws \XF\Db\Exception
	 */
	public function refillStock()
	{
		$this->db()->query('
			UPDATE xf_dbtech_shop_item
			SET stock = maxstock, last_refill_date = UNIX_TIMESTAMP()
			WHERE stock < maxstock
		  		AND stock > -1
				AND refill_time > 0
				AND (last_refill_date + (refill_time * 86400)) <= UNIX_TIMESTAMP()
		');
	}
}