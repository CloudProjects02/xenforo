<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Finder\ItemFinder;
use DBTech\Shop\ItemType\AbstractHandler;
use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;
use XF\Phrase;
use XF\Repository\UserAlertRepository;
use XF\Tree;

class ItemRepository extends Repository
{
	protected array $handlers = [];

	/**
	 * @param array|null $viewableCategoryIds
	 * @param array $limits
	 *
	 * @return ItemFinder
	 * @throws \InvalidArgumentException
	 */
	public function findItemsForOverviewList(?array $viewableCategoryIds = null, array $limits = []): ItemFinder
	{
		$limits = array_replace([
			'visibility' => true,
			'allowOwnPending' => false,
		], $limits);

		/** @var ItemFinder $itemFinder */
		$itemFinder = \XF::app()->finder(ItemFinder::class)
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
	 * @return ItemFinder
	 * @throws \InvalidArgumentException
	 */
	public function findNewItems(?array $viewableCategoryIds = null): ItemFinder
	{
		/** @var ItemFinder $itemFinder */
		$itemFinder = \XF::app()->finder(ItemFinder::class);

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
	 * @return ItemFinder
	 * @throws \InvalidArgumentException
	 */
	public function findTopItems(?array $viewableCategoryIds = null): ItemFinder
	{
		/** @var ItemFinder $itemFinder */
		$itemFinder = \XF::app()->finder(ItemFinder::class);

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
	 * @return ItemFinder
	 * @throws \InvalidArgumentException
	 */
	public function findItemsForWatchedList($userId = null): ItemFinder
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}
		$userId = (int) $userId;

		/** @var ItemFinder $finder */
		$finder = \XF::app()->finder(ItemFinder::class);

		$finder
			->with('fullCategory')
			->with('Watch|' . $userId, true)
			->where('item_state', 'visible')
			->setDefaultOrder('last_update', 'DESC');

		return $finder;
	}

	/**
	 * @param Item $thisItem
	 *
	 * @return ItemFinder
	 * @throws \InvalidArgumentException
	 */
	public function findOtherItemsByAuthor(Item $thisItem): ItemFinder
	{
		/** @var ItemFinder $itemFinder */
		$itemFinder = \XF::app()->finder(ItemFinder::class);

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
	 * @return ItemFinder
	 * @throws \InvalidArgumentException
	 */
	public function findItemsByUser(int $userId, ?array $viewableCategoryIds = null, array $limits = []): ItemFinder
	{
		/** @var ItemFinder $itemFinder */
		$itemFinder = \XF::app()->finder(ItemFinder::class);

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
			'allowOwnPending' => $userId == \XF::visitor()->user_id,
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

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Item> $entities */
		$entities = \XF::app()->finder(ItemFinder::class)->fetch();
		foreach ($entities AS $entity)
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
	public function getHandlers(): array
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
	 * @return AbstractHandler|null
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
	 * @return \XF\Mvc\Entity\AbstractCollection<AbstractHandler>
	 * @throws \Exception
	 */
	public function getItemTypes(bool $onlyActive = false, bool $onlyWithItems = false): AbstractCollection
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

			/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Item> $items */
			$items = \XF::app()->finder(ItemFinder::class)
				->with([
					'Permissions|' . $visitor->permission_combination_id,
					'Category',
					'Category.Permissions|' . $visitor->permission_combination_id,
				])
				->fetch()
			;

			$itemTypes = $itemTypes->filter(function (AbstractHandler $itemType) use ($items, $onlyActive): ?AbstractHandler
			{
				if ($onlyActive)
				{
					$items = $items->filterViewable();
				}

				$activeItems = $items->filter(function (Item $item) use ($itemType): ?Item
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
	 * @return ItemFinder
	 */
	public function findItemsForList(): ItemFinder
	{
		return \XF::app()->finder(ItemFinder::class)->order([['display_order', 'ASC'], ['title', 'ASC']]);
	}

	/**
	 * @return ItemFinder
	 */
	public function findEntriesForPermissionList(): ItemFinder
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

		$arr = $items->pluck(function (Item $e, int $k) use ($forFullView): array
		{
			$title = $e->getTitle();
			if ($title instanceof Phrase)
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
	 * @param \XF\Mvc\Entity\ArrayCollection<AbstractHandler>|null $itemTypes
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getItemTypeTitlePairs(
		bool $filterActive = false,
		bool $onlyWithItems = false,
		?ArrayCollection $itemTypes = null
	): array
	{
		$itemTypes = $itemTypes ?: $this->getItemTypes($filterActive, $onlyWithItems);

		$arr = $itemTypes->pluck(
			fn(AbstractHandler $e, $k): array => [$k, $e->getTitle()->render()],
			false
		);

		asort($arr);

		return $arr;
	}

	/**
	 * @param \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Item>|null $entries
	 * @param int $rootId
	 * @return Tree
	 */
	public function createItemTree(?AbstractCollection $entries = null, int $rootId = 0): Tree
	{
		if ($entries === null)
		{
			$entries = $this->findItemsForList()->fetch();
		}

		return new Tree($entries, 'parent_item_id', $rootId);
	}

	/**
	 * @param int $userId
	 *
	 * @return int
	 */
	public function getUserItemCount(int $userId): int
	{
		return (int) $this->db()->fetchOne("
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
		$flattenedCategoryTree = $flattenedCategoryTree ?: \XF::app()->repository(CategoryRepository::class)->createCategoryTree()->getFlattened(
		);

		$itemsByCategory = [];
		foreach ($flattenedCategoryTree AS $treeEntry)
		{
			foreach ($itemTree AS $record)
			{
				if ($treeEntry['record']->category_id != $record->category_id)
				{
					continue;
				}

				$itemsByCategory[$record->category_id][] = [
					'record' => $record,
					'depth'  => 0,
				];
			}
		}


		return [
			'categories' => $flattenedCategoryTree,
			'items' => $itemsByCategory,
		];
	}

	/**
	 * @param Item $item
	 * @param string $action
	 * @param string $reason
	 * @param array $extra
	 * @param User|null $forceUser
	 *
	 * @return bool
	 */
	public function sendModeratorActionAlert(
		Item $item,
		string $action,
		string $reason = '',
		array $extra = [],
		?User $forceUser = null
	): bool
	{
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
			'link' => \XF::app()->router('public')->buildLink('nopath:dbtech-shop', $item),
			'reason' => $reason,
			'depends_on_addon_id' => 'DBTech/Shop',
		], $extra);

		$alertRepo = \XF::app()->repository(UserAlertRepository::class);
		$alertRepo->alert(
			$forceUser,
			0,
			'',
			'user',
			$item->user_id,
			"dbt_shop_item_$action",
			$extra
		);

		return true;
	}

	/**
	 * @throws \XF\Db\Exception
	 */
	public function refillStock(): void
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