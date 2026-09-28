<?php

namespace DBTech\Shop\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;

/**
 * Class Overview
 *
 * @package DBTech\Shop\ControllerPlugin
 */
class Overview extends AbstractPlugin
{
	/**
	 * @param \DBTech\Shop\Entity\Category|null $category
	 *
	 * @return array
	 */
	public function getCategoryListData(\DBTech\Shop\Entity\Category $category = null): array
	{
		$categoryRepo = $this->getCategoryRepo();
		$categories = $categoryRepo->getViewableCategories();
		$categoryTree = $categoryRepo->createCategoryTree($categories);
		$categoryExtras = $categoryRepo->getCategoryListExtras($categoryTree);

		return [
			'categories' => $categories,
			'categoryTree' => $categoryTree,
			'categoryExtras' => $categoryExtras
		];
	}

	/**
	 * @param array $sourceCategoryIds
	 * @param \DBTech\Shop\Entity\Category|null $category
	 *
	 * @return array
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function getCoreListData(array $sourceCategoryIds, \DBTech\Shop\Entity\Category $category = null): array
	{
		$itemRepo = $this->getItemRepo();

		$allowOwnPending = is_callable([$this->controller, 'hasContentPendingApproval'])
			? $this->controller->hasContentPendingApproval()
			: true;

		$itemFinder = $itemRepo->findItemsForOverviewList($sourceCategoryIds, [
			'allowOwnPending' => $allowOwnPending
		]);

		$filters = $this->getItemFilterInput();
		$this->applyItemFilters($itemFinder, $filters);

		$totalItems = $itemFinder->total();

		$page = $this->filterPage();
		$perPage = $this->options()->dbtechShopItemsPerPage;

		$itemFinder->limitByPage($page, $perPage);
		$items = $itemFinder->fetch()->filterViewable();

		if (!empty($filters['owner_id']))
		{
			$ownerFilter = $this->em()->find('XF:User', $filters['owner_id']);
		}
		else
		{
			$ownerFilter = null;
		}

		if (!empty($filters['item_type_id']))
		{
			/** @noinspection PhpUnhandledExceptionInspection */
			$itemTypeFilter = $this->getItemRepo()->getHandler($filters['item_type_id'], false);
		}
		else
		{
			$itemTypeFilter = null;
		}

		if (!empty($filters['platform']))
		{
			$applicableCategories = $this->getCategoryRepo()->getViewableCategories($category);
			$platformFilter = null;
			foreach ($applicableCategories as $applicableCategory)
			{
				if (isset($applicableCategory['item_filters'][$filters['platform']]))
				{
					$platformFilter = $applicableCategory['item_filters'][$filters['platform']];
					break;
				}
			}
			
			if ($category && $platformFilter === null)
			{
				foreach ($category['item_filters'] as $platformId => $platform)
				{
					if (isset($category['item_filters'][$filters['platform']]))
					{
						$platformFilter = $category['item_filters'][$filters['platform']];
						break;
					}
				}
			}
		}
		else
		{
			$platformFilter = null;
		}

		$canInlineMod = false;
		foreach ($items AS $item)
		{
			/** @var \DBTech\Shop\Entity\Item $item */
			if ($item->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		return [
			'items' => $items,
			'filters' => $filters,
			'ownerFilter' => $ownerFilter,
			'itemTypeFilter' => $itemTypeFilter,
			'platformFilter' => $platformFilter,
			'canInlineMod' => $canInlineMod,

			'total' => $totalItems,
			'page' => $page,
			'perPage' => $perPage
		];
	}
	
	/**
	 * @param \DBTech\Shop\Finder\Item $itemFinder
	 * @param array $filters
	 */
	public function applyItemFilters(\DBTech\Shop\Finder\Item $itemFinder, array $filters)
	{
		if (!empty($filters['prefix_id']))
		{
			$itemFinder->where('prefix_id', (int)$filters['prefix_id']);
		}

		/*
		if (!empty($filters['type']))
		{
			switch ($filters['type'])
			{
				case 'free':
					$itemFinder->where('is_paid', 0);
					break;

				case 'paid':
					$itemFinder->where('is_paid', 1);
					break;
			}
		}
		*/

		if (!empty($filters['owner_id']))
		{
			$itemFinder->where('user_id', (int)$filters['owner_id']);
		}

		if (!empty($filters['item_type_id']))
		{
			$itemFinder->where('item_type_id', $filters['item_type_id']);
		}

		if (!empty($filters['platform']))
		{
			$filterAssociations = $this->finder('DBTech\Shop:ItemFilterMap')
				->where('filter_id', $filters['platform']);
			
			$itemFinder->where('item_id', $filterAssociations->fetch()->pluckNamed('item_id', 'item_id'));
		}

		$sorts = $this->getAvailableItemSorts();

		if (!empty($filters['order']) && isset($sorts[$filters['order']]))
		{
			$itemFinder->order($sorts[$filters['order']], $filters['direction']);
		}
	}
	
	/**
	 * @return array
	 */
	public function getItemFilterInput(): array
	{
		$filters = [];

		$input = $this->filter([
			'prefix_id' => 'uint',
			'type' => 'str',
			'owner' => 'str',
			'owner_id' => 'uint',
			'item_type_id' => 'str',
			'platform' => 'str',
			'order' => 'str',
			'direction' => 'str'
		]);

		if ($input['prefix_id'])
		{
			$filters['prefix_id'] = $input['prefix_id'];
		}

		if ($input['type'] && ($input['type'] == 'free' || $input['type'] == 'paid'))
		{
			$filters['type'] = $input['type'];
		}

		if ($input['owner_id'])
		{
			$filters['owner_id'] = $input['owner_id'];
		}
		elseif ($input['owner'])
		{
			$user = $this->em()->findOne('XF:User', ['username' => $input['owner']]);
			if ($user)
			{
				$filters['owner_id'] = $user->user_id;
			}
		}

		if ($input['item_type_id'])
		{
			$filters['item_type_id'] = $input['item_type_id'];
		}
		
		if ($input['platform'])
		{
			$filters['platform'] = $input['platform'];
		}

		$sorts = $this->getAvailableItemSorts();

		if ($input['order'] && isset($sorts[$input['order']]))
		{
			if (!in_array($input['direction'], ['asc', 'desc']))
			{
				$input['direction'] = 'desc';
			}

			$defaultOrder = $this->options()->dbtechShopListDefaultOrder ?: 'last_update';
			$defaultDir = in_array($defaultOrder, ['title', 'display_order']) ? 'asc' : 'desc';

			if ($input['order'] != $defaultOrder || $input['direction'] != $defaultDir)
			{
				$filters['order'] = $input['order'];
				$filters['direction'] = $input['direction'];
			}
		}

		return $filters;
	}
	
	/**
	 * @return array
	 */
	public function getAvailableItemSorts(): array
	{
		// maps [name of sort] => field in/relative to Item entity
		return [
			'last_update' => 'last_update',
			'creation_date' => 'creation_date',
			'rating_weighted' => 'rating_weighted',
			'purchases' => 'purchases',
			'title' => 'title',
			'display_order' => 'display_order'
		];
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Category|null $category
	 *
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 */
	public function actionFilters(?\DBTech\Shop\Entity\Category $category = null)
	{
		$filters = $this->getItemFilterInput();

		if ($this->filter('apply', 'bool'))
		{
			return $this->redirect($this->buildLink(
				$category ? 'dbtech-shop/categories' : 'dbtech-shop',
				$category,
				$filters
			));
		}

		if (!empty($filters['owner_id']))
		{
			$ownerFilter = $this->em()->find('XF:User', $filters['owner_id']);
		}
		else
		{
			$ownerFilter = null;
		}
		
		$applicableCategories = $this->getCategoryRepo()->getViewableCategories($category);
		$applicableCategoryIds = $applicableCategories->keys();
		if ($category)
		{
			$applicableCategoryIds[] = $category->category_id;
		}

		$availablePrefixIds = $this->getCategoryPrefixRepo()->getPrefixIdsInContent($applicableCategoryIds);
		$prefixes = $this->getItemPrefixRepo()->findPrefixesForList()
			->where('prefix_id', $availablePrefixIds)
			->fetch();

		$defaultOrder = $this->options()->dbtechShopListDefaultOrder ?: 'last_update';
		$defaultDir = in_array($defaultOrder, ['title', 'display_order']) ? 'asc' : 'desc';

		if (empty($filters['order']))
		{
			$filters['order'] = $defaultOrder;
		}
		if (empty($filters['direction']))
		{
			$filters['direction'] = $defaultDir;
		}
		
		$platformFilter = [];
		foreach ($applicableCategories as $applicableCategory)
		{
			foreach ($applicableCategory['item_filters'] as $platformId => $platform)
			{
				$platformFilter[$platformId] = $platform;
			}
		}
		
		if ($category)
		{
			foreach ($category['item_filters'] as $platformId => $platform)
			{
				$platformFilter[$platformId] = $platform;
			}
		}
		asort($platformFilter);
		
		$viewParams = [
			'category' => $category,
			'prefixesGrouped' => $prefixes->groupBy('prefix_group_id'),
			'filters' => $filters,
			'ownerFilter' => $ownerFilter,
			'platformFilter' => $platformFilter,
		];
		return $this->view('DBTech\Shop:Filters', 'dbtech_shop_filters', $viewParams);
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Item|\XF\Mvc\Entity\Repository
	 */
	protected function getItemRepo()
	{
		return $this->repository('DBTech\Shop:Item');
	}
	
	/**
	 * @return \DBTech\Shop\Repository\ItemPrefix|\XF\Mvc\Entity\Repository
	 */
	protected function getItemPrefixRepo()
	{
		return $this->repository('DBTech\Shop:ItemPrefix');
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Category|\XF\Mvc\Entity\Repository
	 */
	protected function getCategoryRepo()
	{
		return $this->repository('DBTech\Shop:Category');
	}
	
	/**
	 * @return \DBTech\Shop\Repository\CategoryPrefix|\XF\Mvc\Entity\Repository
	 */
	protected function getCategoryPrefixRepo()
	{
		return $this->repository('DBTech\Shop:CategoryPrefix');
	}
}