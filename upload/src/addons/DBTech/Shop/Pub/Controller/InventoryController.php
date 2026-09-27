<?php

namespace DBTech\Shop\Pub\Controller;

use DBTech\Shop\Entity\Category;
use DBTech\Shop\Entity\Item;
use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Finder\ItemFilterMapFinder;
use DBTech\Shop\ItemType\AbstractHandler;
use DBTech\Shop\ItemType\ConfigurableInterface;
use DBTech\Shop\Pub\View;
use DBTech\Shop\Repository\CategoryPrefixRepository;
use DBTech\Shop\Repository\CategoryRepository;
use DBTech\Shop\Repository\ItemPrefixRepository;
use DBTech\Shop\Repository\ItemRepository;
use DBTech\Shop\Repository\PurchaseRepository;
use DBTech\Shop\Service\Purchase\SellbackService;
use DBTech\Shop\Service\Purchase\TransferService;
use XF\Entity\User;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Phrase;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;
use XF\Repository\UserRepository;

class InventoryController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		if (!$visitor->canViewDbtechShopItems($error))
		{
			throw $this->exception($this->noPermission($error));
		}
	}


	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIndex(ParameterBag $params): AbstractReply
	{
		if ($params->purchase_id)
		{
			$purchase = $this->assertViewablePurchase($params->purchase_id, [
				'Buyer',
			]);

			$viewParams = [
				'purchase' => $purchase,
				'item'     => $purchase->Item,
			];
			return $this->view(
				View\Inventory\Purchase\ViewView::class,
				'dbtech_shop_inventory_purchase_view',
				$viewParams
			);
		}

		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/inventory'));

		$userId = $this->filter('user_id', 'uint');
		$inventoryUser = $userId ? \XF::app()->em()->find(User::class, $userId) : \XF::visitor();
		if (!$inventoryUser)
		{
			return $this->error(\XF::phrase('requested_user_not_found'));
		}

		$inventoryFinder = \XF::app()->repository(PurchaseRepository::class)
			->findInventoryForUser($inventoryUser->user_id)
			->order([['expiry_date', 'ASC'], ['dateline', 'DESC']])
		;

		$filters = $this->getInventoryFilterInput();
		$this->applyInventoryFilters($inventoryFinder, $filters);

		$totalItems = $inventoryFinder->total();

		$page = $this->filterPage();
		$perPage = \XF::app()->options()->dbtechShopInventoryItemsPerPage;
		//		$perPage = 2;

		$inventoryFinder->limitByPage($page, $perPage);

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $inventory */
		$inventory = $inventoryFinder->fetch()->filterViewable();

		if ($inventoryUser->user_id != \XF::visitor()->user_id)
		{
			// Hide hidden purchases from others' inventories
			$inventory = $inventory->filter(function (Purchase $purchase): ?Purchase
			{
				if (!$purchase->isDisplayed())
				{
					return null;
				}

				return $purchase;
			});
		}

		if (!empty($filters['owner_id']))
		{
			$ownerFilter = \XF::app()->em()->find(User::class, $filters['owner_id']);
		}
		else
		{
			$ownerFilter = null;
		}

		if (!empty($filters['item_id']))
		{
			$itemFilter = \XF::app()->em()->find(Item::class, $filters['item_id']);
		}
		else
		{
			$itemFilter = null;
		}

		if (!empty($filters['category_id']))
		{
			$categoryFilter = \XF::app()->em()->find(Category::class, $filters['category_id']);
		}
		else
		{
			$categoryFilter = null;
		}

		if (!empty($filters['item_type_id']))
		{
			/** @noinspection PhpUnhandledExceptionInspection */
			$itemTypeFilter = \XF::app()->repository(ItemRepository::class)
				->getHandler($filters['item_type_id'])
			;
		}
		else
		{
			$itemTypeFilter = null;
		}

		if (!empty($filters['platform']))
		{
			$applicableCategories = \XF::app()->repository(CategoryRepository::class)
				->getViewableCategories()
			;
			$platformFilter = null;
			foreach ($applicableCategories AS $applicableCategory)
			{
				if (isset($applicableCategory['item_filters'][$filters['platform']]))
				{
					$platformFilter = $applicableCategory['item_filters'][$filters['platform']];
					break;
				}
			}
		}
		else
		{
			$platformFilter = null;
		}

		$currentPurchases = $inventory->filter(function (Purchase $purchase): ?Purchase
		{
			if (!$purchase->isExpired())
			{
				return $purchase;
			}

			return null;
		});
		$expiredPurchases = $inventory->filter(function (Purchase $purchase): ?Purchase
		{
			if ($purchase->isExpired())
			{
				return $purchase;
			}

			return null;
		});

		$inventoryGrouped = $currentPurchases->groupBy('active');

		$canInlineMod = false;
		foreach ($inventory AS $purchase)
		{
			/** @var Purchase $purchase */
			if ($purchase->canUseInlineModeration())
			{
				$canInlineMod = true;
				break;
			}
		}

		$viewParams = [
			'inventory' => $inventory,

			'filters' => $filters,
			'ownerFilter' => $ownerFilter,
			'itemFilter' => $itemFilter,
			'categoryFilter' => $categoryFilter,
			'itemTypeFilter' => $itemTypeFilter,
			'platformFilter' => $platformFilter,
			'canInlineMod' => $canInlineMod,

			'inventoryUser' => $inventoryUser,
			'activePurchases' => $inventoryGrouped[1] ?? [],
			'inactivePurchases' => $inventoryGrouped[0] ?? [],
			'expiredPurchases' => $expiredPurchases,

			'total' => $totalItems,
			'page' => $page,
			'perPage' => $perPage,
		];
		return $this->view(
			View\Inventory\IndexView::class,
			'dbtech_shop_inventory',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionSettings(ParameterBag $params): AbstractReply
	{
		$purchase = $this->assertViewablePurchase($params->purchase_id);
		if (!$purchase->canEditSettings())
		{
			return $this->error(\XF::phrase('dbtech_shop_cannot_edit_settings_for_this_purchase'));
		}

		if ($this->isPost())
		{
			/** @var AbstractHandler $handler */
			$handler = $purchase->handler;

			$isActive = $this->filter('active', 'bool');

			if ($purchase->isActive() && !$isActive)
			{
				$success = $handler->deactivate($error);
				if (!$success)
				{
					return $this->error($error);
				}
			}
			else if (!$purchase->isActive() && $isActive)
			{
				$success = $handler->activate($error);
				if (!$success)
				{
					return $this->error($error);
				}
			}

			$purchase->hidden = $this->filter('hidden', 'bool');
			$purchase->saveIfChanged();

			return $this->redirect($this->buildLink('dbtech-shop/inventory'));
		}

		$viewParams = [
			'purchase' => $purchase,
			'item' => $purchase->Item,
		];
		return $this->view(
			View\Inventory\SettingsView::class,
			'dbtech_shop_inventory_settings',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionDiscard(ParameterBag $params): AbstractReply
	{
		$purchase = $this->assertViewablePurchase($params->purchase_id);
		if (!$purchase->canDiscard())
		{
			return $this->error(\XF::phrase('dbtech_shop_cannot_discard_this_purchase'));
		}

		if ($this->isPost())
		{
			/** @var AbstractHandler $handler */
			$handler = $purchase->handler;

			$success = $handler->discard($error);
			if (!$success)
			{
				return $this->error($error);
			}

			return $this->redirect(
				$this->buildLink('dbtech-shop/inventory'),
				\XF::phrase('dbtech_shop_item_discarded')
			);
		}

		$viewParams = [
			'purchase' => $purchase,
			'item' => $purchase->Item,
		];
		return $this->view(
			View\Inventory\DiscardView::class,
			'dbtech_shop_inventory_discard',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionSell(ParameterBag $params): AbstractReply
	{
		$purchase = $this->assertViewablePurchase($params->purchase_id);
		if (!$purchase->canSellBack())
		{
			return $this->error(\XF::phrase('dbtech_shop_cannot_sell_back_purchase_please_discard'));
		}

		if ($this->isPost())
		{
			$purchaseService = \XF::app()->service(SellbackService::class, $purchase);

			if (!$purchaseService->validate($errors))
			{
				return $this->error($errors);
			}

			$purchaseService->save();

			return $this->redirect(
				$this->buildLink('dbtech-shop/inventory'),
				\XF::phrase('dbtech_shop_item_sold_back')
			);
		}

		$viewParams = [
			'purchase' => $purchase,
			'item' => $purchase->Item,
			'currency' => $purchase->Item->BuybackCurrency,
		];
		return $this->view(
			View\Inventory\SellView::class,
			'dbtech_shop_inventory_sell',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionGift(ParameterBag $params): AbstractReply
	{
		$purchase = $this->assertViewablePurchase($params->purchase_id);
		if (!$purchase->canGift())
		{
			return $this->error(\XF::phrase('dbtech_shop_cannot_gift_this_purchase'));
		}

		if ($this->isPost())
		{
			$toUser = \XF::app()->repository(UserRepository::class)
				->getUserByNameOrEmail($this->filter('recipient', 'str'))
			;
			if (!$toUser)
			{
				return $this->error(\XF::phrase('requested_user_not_found'));
			}

			if ($toUser->user_id == \XF::visitor()->user_id)
			{
				return $this->error(\XF::phrase('dbtech_shop_cannot_gift_item_to_self'));
			}

			$purchaseService = \XF::app()->service(TransferService::class, $purchase, $toUser);
			$purchaseService->setIsGift(true);
			$purchaseService->setMessage($this->filter('message', 'str'));

			if ($purchase->canGiftAsNew())
			{
				$purchaseService->removeConfiguration($this->filter('remove_configuration', 'bool', false));
			}

			if (!$purchaseService->validate($errors))
			{
				return $this->error($errors);
			}

			$purchaseService->save();

			return $this->redirect(
				$this->buildLink('dbtech-shop/inventory'),
				\XF::phrase('dbtech_shop_item_gifted')
			);
		}

		$viewParams = [
			'purchase' => $purchase,
			'item' => $purchase->Item,
		];
		return $this->view(
			View\Inventory\GiftView::class,
			'dbtech_shop_inventory_gift',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionConfigure(ParameterBag $params): AbstractReply
	{
		$purchase = $this->assertViewablePurchase($params->purchase_id);
		if (!$purchase->canConfigure())
		{
			return $this->error(\XF::phrase('dbtech_shop_cannot_configure_this_purchase'));
		}

		if ($this->isPost())
		{
			/** @var AbstractHandler|ConfigurableInterface $handler */
			$handler = $purchase->handler;

			$configuration = $this->filter('code', 'array');
			$configuration = $handler->filterUserConfig($configuration);

			$errors = null;
			if (!$handler->validateUserConfig($configuration, $errors))
			{
				return $this->error($errors);
			}

			$handler->configure($configuration);

			return $this->redirect(
				$this->buildLink('dbtech-shop/inventory'),
				\XF::phrase('dbtech_shop_item_configured')
			);
		}

		$viewParams = [
			'purchase' => $purchase,
			'item' => $purchase->Item,
		];
		return $this->view(
			View\Inventory\ConfigureView::class,
			'dbtech_shop_inventory_configure',
			$viewParams
		);
	}

	/**
	 * @param Finder $inventoryFinder
	 * @param array $filters
	 */
	public function applyInventoryFilters(Finder $inventoryFinder, array $filters): void
	{
		if (!empty($filters['prefix_id']))
		{
			$inventoryFinder->where('Item.prefix_id', (int) $filters['prefix_id']);
		}

		/*
		if (!empty($filters['type']))
		{
			switch ($filters['type'])
			{
				case 'free':
					$inventoryFinder->where('is_paid', 0);
					break;

				case 'paid':
					$inventoryFinder->where('is_paid', 1);
					break;
			}
		}
		*/

		if (!empty($filters['owner_id']))
		{
			$inventoryFinder->where('Item.user_id', (int) $filters['owner_id']);
		}

		if (!empty($filters['item_id']))
		{
			$inventoryFinder->where('item_id', $filters['item_id']);
		}

		if (!empty($filters['category_id']))
		{
			$inventoryFinder->where('Item.category_id', $filters['category_id']);
		}

		if (!empty($filters['item_type_id']))
		{
			$inventoryFinder->where('Item.item_type_id', $filters['item_type_id']);
		}

		if (!empty($filters['platform']))
		{
			$filterAssociations = \XF::app()->finder(ItemFilterMapFinder::class)
				->where('filter_id', $filters['platform'])
			;

			$inventoryFinder->where('item_id', $filterAssociations->fetch()->pluckNamed('item_id', 'item_id'));
		}

		/*
		$sorts = $this->getAvailableInventorySorts();

		if (!empty($filters['order']) && isset($sorts[$filters['order']]))
		{
			$inventoryFinder->order($sorts[$filters['order']], $filters['direction']);
		}
		*/
	}

	/**
	 * @return array
	 */
	public function getInventoryFilterInput(): array
	{
		$filters = [];

		$input = $this->filter([
			'prefix_id' => 'uint',
			'type' => 'str',
			'owner' => 'str',
			'owner_id' => 'uint',
			'item_id' => 'uint',
			'category_id' => 'uint',
			'item_type_id' => 'str',
			'platform' => 'str',
			'order' => 'str',
			'direction' => 'str',
		]);

		if ($input['prefix_id'])
		{
			$filters['prefix_id'] = $input['prefix_id'];
		}

		if (($input['type'] == 'free' || $input['type'] == 'paid'))
		{
			$filters['type'] = $input['type'];
		}

		if ($input['owner_id'])
		{
			$filters['owner_id'] = $input['owner_id'];
		}
		else if ($input['owner'])
		{
			$user = \XF::app()->em()->findOne(User::class, ['username' => $input['owner']]);
			if ($user)
			{
				$filters['owner_id'] = $user->user_id;
			}
		}

		if ($input['item_id'])
		{
			$filters['item_id'] = $input['item_id'];
		}

		if ($input['category_id'])
		{
			$filters['category_id'] = $input['category_id'];
		}

		if ($input['item_type_id'])
		{
			$filters['item_type_id'] = $input['item_type_id'];
		}

		if ($input['platform'])
		{
			$filters['platform'] = $input['platform'];
		}

		/*
		$sorts = $this->getAvailableInventorySorts();

		if ($input['order'] && isset($sorts[$input['order']]))
		{
			if (!in_array($input['direction'], ['asc', 'desc']))
			{
				$input['direction'] = 'desc';
			}

			$defaultOrder = \XF::app()->options()->dbtechShopListDefaultOrder ?: 'last_update';
			$defaultDir = in_array($defaultOrder, ['title', 'display_order']) ? 'asc' : 'desc';

			if ($input['order'] != $defaultOrder || $input['direction'] != $defaultDir)
			{
				$filters['order'] = $input['order'];
				$filters['direction'] = $input['direction'];
			}
		}
		*/

		return $filters;
	}

	/**
	 * @return array
	 */
	public function getAvailableInventorySorts(): array
	{
		// maps [name of sort] => field in/relative to Item entity
		return [
			'last_update' => 'last_update',
			'creation_date' => 'creation_date',
			'rating_weighted' => 'rating_weighted',
			'purchases' => 'purchases',
			'title' => 'title',
			'display_order' => 'display_order',
		];
	}

	/**
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionFilters(): AbstractReply
	{
		$filters = $this->getInventoryFilterInput();

		if ($this->filter('apply', 'bool'))
		{
			return $this->redirect($this->buildLink('dbtech-shop/inventory', null, $filters));
		}

		if (!empty($filters['owner_id']))
		{
			$ownerFilter = \XF::app()->em()->find(User::class, $filters['owner_id']);
		}
		else
		{
			$ownerFilter = null;
		}

		$itemRepo = \XF::app()->repository(ItemRepository::class);

		$purchasedItems = \XF::app()->db()->fetchPairs('
			SELECT purchase.item_id, item.item_type_id
			FROM xf_dbtech_shop_purchase AS purchase
			LEFT JOIN xf_dbtech_shop_item AS item USING(item_id)
			WHERE purchase.user_id = ?
		', \XF::visitor()->user_id);

		$itemTree = $itemRepo
			->findItemsForList()
			->where('item_id', array_keys($purchasedItems))
			->fetch()
		;

		$itemTypes = $itemRepo->getItemTypes(true)
			->filter(function (AbstractHandler $itemType) use ($purchasedItems): ?AbstractHandler
			{
				if (!in_array($itemType->getContentType(), $purchasedItems))
				{
					return null;
				}

				return $itemType;
			})
		;
		$itemTypes = $itemRepo->getItemTypeTitlePairs(true, false, $itemTypes);

		$itemsByCategory = \XF::app()->repository(ItemRepository::class)
			->getItemsByCategory($itemTree)
		;

		$applicableCategories = \XF::app()->repository(CategoryRepository::class)
			->getViewableCategories()
		;
		$applicableCategoryIds = $applicableCategories->keys();

		$availablePrefixIds = \XF::app()->repository(CategoryPrefixRepository::class)
			->getPrefixIdsInContent($applicableCategoryIds)
		;
		$prefixes = \XF::app()->repository(ItemPrefixRepository::class)
			->findPrefixesForList()
			->where('prefix_id', $availablePrefixIds)
			->fetch();

		/*
		$defaultOrder = \XF::app()->options()->dbtechShopListDefaultOrder ?: 'last_update';
		$defaultDir = in_array($defaultOrder, ['title', 'display_order']) ? 'asc' : 'desc';

		if (empty($filters['order']))
		{
			$filters['order'] = $defaultOrder;
		}
		if (empty($filters['direction']))
		{
			$filters['direction'] = $defaultDir;
		}
		*/

		$platformFilter = [];
		foreach ($applicableCategories AS $applicableCategory)
		{
			foreach ($applicableCategory['item_filters'] AS $platformId => $platform)
			{
				$platformFilter[$platformId] = $platform;
			}
		}

		asort($platformFilter);

		$viewParams = [
			'prefixesGrouped' => $prefixes->groupBy('prefix_group_id'),
			'filters' => $filters,
			'ownerFilter' => $ownerFilter,
			'platformFilter' => $platformFilter,
			'itemsByCategory' => $itemsByCategory,
			'categoryTree' => $itemsByCategory['categories'],
			'itemTypes' => $itemTypes,
		];
		return $this->view(
			View\Inventory\FiltersView::class,
			'dbtech_shop_inventory_filters',
			$viewParams
		);
	}

	/**
	 * @param int|null $purchaseId
	 * @param array $extraWith
	 *
	 * @return Purchase
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewablePurchase(?int $purchaseId, array $extraWith = []): Purchase
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'Item.Permissions|' . $visitor->permission_combination_id;
		$extraWith[] = 'Item.User';
		$extraWith[] = 'Item.Category';
		$extraWith[] = 'Item.Category.Permissions|' . $visitor->permission_combination_id;
		$extraWith[] = 'Item.Discussion';
		$extraWith[] = 'Item.Discussion.Forum';
		$extraWith[] = 'Item.Discussion.Forum.Node';
		$extraWith[] = 'Item.Discussion.Forum.Node.Permissions|' . $visitor->permission_combination_id;

		$purchase = \XF::app()->em()->find(Purchase::class, $purchaseId, $extraWith);
		if (!$purchase)
		{
			throw $this->exception($this->notFound(\XF::phrase('dbtech_shop_requested_purchase_not_found')));
		}

		if (!$purchase->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $purchase;
	}

	/**
	 * @param array $activities
	 *
	 * @return Phrase
	 */
	public static function getActivityDetails(array $activities): Phrase
	{
		return \XF::phrase('dbtech_shop_viewing_inventory');
	}
}