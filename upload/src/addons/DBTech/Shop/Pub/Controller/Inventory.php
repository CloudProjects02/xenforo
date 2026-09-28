<?php

namespace DBTech\Shop\Pub\Controller;

use DBTech\Shop\ItemType\AbstractHandler;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Inventory
 *
 * @package DBTech\Shop\Pub\Controller
 */
class Inventory extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
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
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIndex(ParameterBag $params)
	{
		if ($params->purchase_id)
		{
			$purchase = $this->assertViewablePurchase($params->purchase_id, [
				'Buyer',
			]);

			$viewParams = [
				'purchase' => $purchase,
				'item'     => $purchase->Item
			];
			return $this->view('DBTech\Shop:Inventory\Purchase\View', 'dbtech_shop_inventory_purchase_view', $viewParams);
		}

		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/inventory'));

		$userId = $this->filter('user_id', 'uint');
		$inventoryUser = $userId ? $this->em()->find('XF:User', $userId) : \XF::visitor();
		if (!$inventoryUser)
		{
			return $this->error(\XF::phrase('requested_user_not_found'));
		}

		$inventoryFinder = $this->repository('DBTech\Shop:Purchase')
			->findInventoryForUser($inventoryUser->user_id)
			->order([['expiry_date', 'ASC'], ['dateline', 'DESC']], 'ASC')
		;

		$filters = $this->getInventoryFilterInput();
		$this->applyInventoryFilters($inventoryFinder, $filters);

		$totalItems = $inventoryFinder->total();

		$page = $this->filterPage();
		$perPage = $this->options()->dbtechShopInventoryItemsPerPage;
//		$perPage = 2;

		$inventoryFinder->limitByPage($page, $perPage);

		/** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $inventory */
		$inventory = $inventoryFinder->fetch()->filterViewable();

		if ($inventoryUser->user_id != \XF::visitor()->user_id)
		{
			// Hide hidden purchases from others' inventories
			$inventory = $inventory->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
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
			$ownerFilter = $this->em()->find('XF:User', $filters['owner_id']);
		}
		else
		{
			$ownerFilter = null;
		}

		if (!empty($filters['item_id']))
		{
			$itemFilter = $this->em()->find('DBTech\Shop:Item', $filters['item_id']);
		}
		else
		{
			$itemFilter = null;
		}

		if (!empty($filters['category_id']))
		{
			$categoryFilter = $this->em()->find('DBTech\Shop:Category', $filters['category_id']);
		}
		else
		{
			$categoryFilter = null;
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
			$applicableCategories = $this->getCategoryRepo()->getViewableCategories();
			$platformFilter = null;
			foreach ($applicableCategories as $applicableCategory)
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

		$currentPurchases = $inventory->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
		{
			if (!$purchase->isExpired())
			{
				return $purchase;
			}
			
			return null;
		});
		$expiredPurchases = $inventory->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
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
			/** @var \DBTech\Shop\Entity\Purchase $purchase */
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
			'perPage' => $perPage
		];
		return $this->view('DBTech\Shop:Inventory\Index', 'dbtech_shop_inventory', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionSettings(ParameterBag $params)
	{
		$purchase = $this->assertViewablePurchase($params->purchase_id);
		if (!$purchase->canEditSettings())
		{
			return $this->error(\XF::phrase('dbtech_shop_cannot_edit_settings_for_this_purchase'));
		}
		
		if ($this->isPost())
		{
			/** @var \DBTech\Shop\ItemType\AbstractHandler $handler */
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
			elseif (!$purchase->isActive() && $isActive)
			{
				// Disable other items of the same type before activating
				if ($purchase->Item->item_type_id == 'usernameitem')
				{
					$this->disableOtherShopUsernameIcons($purchase);
				}
				elseif ($purchase->Item->item_type_id == 'avatarframe')
				{
					$this->disableOtherShopAvatarIcons($purchase);
				}
				elseif ($purchase->Item->item_type_id == 'profileeffects')
				{
					$this->disableOtherProfileEffects($purchase);
				}

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
			'item' => $purchase->Item
		];
		return $this->view('DBTech\Shop:Inventory\Settings', 'dbtech_shop_inventory_settings', $viewParams);
	}

	private function disableOtherProfileEffects(\DBTech\Shop\Entity\Purchase $purchase)
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/**
		 * @var Purchase $repo
		 */
		$purchases = $this->repository('DBTech\Shop:Purchase')->findPurchasesForUser($visitor->user_id)
			->where('Item.item_type_id','profileeffects')
			->where('purchase_id','<>',$purchase->purchase_id)
			->fetch();

		foreach ($purchases as $purchase)
		{
			$handler = $purchase->handler;
			$error = '';
			$handler->deactivate($error);
		}
	}

	private function disableOtherShopAvatarIcons(\DBTech\Shop\Entity\Purchase $purchase)
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/**
		 * @var Purchase $repo
		 */
		$purchases = $this->repository('DBTech\Shop:Purchase')->findPurchasesForUser($visitor->user_id)
			->where('Item.item_type_id','avatarframe')
			->where('purchase_id','<>',$purchase->purchase_id)
			->fetch();

		foreach ($purchases as $purchase)
		{
			$handler = $purchase->handler;
			$error = '';
			$handler->deactivate($error);
		}
	}

	private function disableOtherShopUsernameIcons(\DBTech\Shop\Entity\Purchase $purchase)
    {
        /** @var \DBTech\Shop\XF\Entity\User $visitor */
        $visitor = \XF::visitor();

        /**
         * @var Purchase $repo
         */
        $purchases = $this->repository('DBTech\Shop:Purchase')->findPurchasesForUser($visitor->user_id)
            ->where('Item.item_type_id','usernameitem')
            ->where('purchase_id','<>',$purchase->purchase_id)
            ->fetch();

        foreach ($purchases as $purchase)
        {
            $handler = $purchase->handler;
            $error = '';
            $handler->deactivate($error);
        }
    }
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionDiscard(ParameterBag $params)
	{
		$purchase = $this->assertViewablePurchase($params->purchase_id);
		if (!$purchase->canDiscard())
		{
			return $this->error(\XF::phrase('dbtech_shop_cannot_discard_this_purchase'));
		}
		
		if ($this->isPost())
		{
			/** @var \DBTech\Shop\ItemType\AbstractHandler $handler */
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
			'item' => $purchase->Item
		];
		return $this->view('DBTech\Shop:Inventory\Discard', 'dbtech_shop_inventory_discard', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionSell(ParameterBag $params)
	{
		$purchase = $this->assertViewablePurchase($params->purchase_id);
		if (!$purchase->canSellBack())
		{
			return $this->error(\XF::phrase('dbtech_shop_cannot_sell_back_purchase_please_discard'));
		}
		
		if ($this->isPost())
		{
			/** @var \DBTech\Shop\Service\Purchase\Sellback $purchaseService */
			$purchaseService = \XF::app()->service('DBTech\Shop:Purchase\Sellback', $purchase);
			
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
		return $this->view('DBTech\Shop:Inventory\Sell', 'dbtech_shop_inventory_sell', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionGift(ParameterBag $params)
	{
		$purchase = $this->assertViewablePurchase($params->purchase_id);
		if (!$purchase->canGift())
		{
			return $this->error(\XF::phrase('dbtech_shop_cannot_gift_this_purchase'));
		}
		
		if ($this->isPost())
		{
			/** @var \XF\Entity\User $toUser */
			$toUser = $this->repository('XF:User')
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
			
			/** @var \DBTech\Shop\Service\Purchase\Transfer $purchaseService */
			$purchaseService = \XF::app()->service('DBTech\Shop:Purchase\Transfer', $purchase, $toUser);
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
			'item' => $purchase->Item
		];
		return $this->view('DBTech\Shop:Inventory\Gift', 'dbtech_shop_inventory_gift', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionConfigure(ParameterBag $params)
	{
		$purchase = $this->assertViewablePurchase($params->purchase_id);
		if (!$purchase->canConfigure())
		{
			return $this->error(\XF::phrase('dbtech_shop_cannot_configure_this_purchase'));
		}
		
		if ($this->isPost())
		{
			/** @var \DBTech\Shop\ItemType\AbstractHandler|\DBTech\Shop\ItemType\ConfigurableInterface $handler */
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
			'item' => $purchase->Item
		];
		return $this->view('DBTech\Shop:Inventory\Configure', 'dbtech_shop_inventory_configure', $viewParams);
	}

	/**
	 * @param \XF\Mvc\Entity\Finder $inventoryFinder
	 * @param array $filters
	 */
	public function applyInventoryFilters(\XF\Mvc\Entity\Finder $inventoryFinder, array $filters)
	{
		if (!empty($filters['prefix_id']))
		{
			$inventoryFinder->where('Item.prefix_id', (int)$filters['prefix_id']);
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
			$inventoryFinder->where('Item.user_id', (int)$filters['owner_id']);
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
			$filterAssociations = $this->finder('DBTech\Shop:ItemFilterMap')
				->where('filter_id', $filters['platform']);

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

			$defaultOrder = $this->options()->dbtechShopListDefaultOrder ?: 'last_update';
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
			'display_order' => 'display_order'
		];
	}

	/**
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \Exception
	 */
	public function actionFilters()
	{
		$filters = $this->getInventoryFilterInput();

		if ($this->filter('apply', 'bool'))
		{
			return $this->redirect($this->buildLink('dbtech-shop/inventory', null, $filters));
		}

		if (!empty($filters['owner_id']))
		{
			$ownerFilter = $this->em()->find('XF:User', $filters['owner_id']);
		}
		else
		{
			$ownerFilter = null;
		}

		$itemRepo = $this->getItemRepo();

		$purchasedItems = $this->app->db()->fetchPairs('
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

		$itemTypes = $itemRepo->getItemTypes(true, false)
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

		$itemsByCategory = $this->getItemRepo()->getItemsByCategory($itemTree);

		$applicableCategories = $this->getCategoryRepo()->getViewableCategories();
		$applicableCategoryIds = $applicableCategories->keys();

		$availablePrefixIds = $this->getCategoryPrefixRepo()->getPrefixIdsInContent($applicableCategoryIds);
		$prefixes = $this->getItemPrefixRepo()->findPrefixesForList()
			->where('prefix_id', $availablePrefixIds)
			->fetch();

		/*
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
		*/

		$platformFilter = [];
		foreach ($applicableCategories as $applicableCategory)
		{
			foreach ($applicableCategory['item_filters'] as $platformId => $platform)
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
		return $this->view('DBTech\Shop:Inventory\Filters', 'dbtech_shop_inventory_filters', $viewParams);
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Currency|\XF\Mvc\Entity\Repository
	 */
	protected function getCurrencyRepo()
	{
		return $this->repository('DBTech\Shop:Currency');
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
	
	/**
	 * @param int|null $purchaseId
	 * @param array $extraWith
	 *
	 * @return \DBTech\Shop\Entity\Purchase|\XF\Mvc\Entity\Entity
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewablePurchase(?int $purchaseId, array $extraWith = [])
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
		
		/** @var \DBTech\Shop\Entity\Purchase $purchase */
		$purchase = $this->em()->find('DBTech\Shop:Purchase', $purchaseId, $extraWith);
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
	 * @return \XF\Phrase
	 */
	public static function getActivityDetails(array $activities): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_viewing_inventory');
	}
}