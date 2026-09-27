<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Entity;

use DBTech\Shop\Entity\Currency;
use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Finder\ItemFinder;
use DBTech\Shop\Repository\PurchaseRepository;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\PrintableException;

/**
 * @extends \XF\Entity\User
 *
 * COLUMNS
 * @property mixed|null dbtech_shop_purchase_
 * @property int dbtech_shop_purchases
 * @property int dbtech_shop_immunity
 * @property int dbtech_shop_pendingtrades
 * @property int dbtech_shop_item_count
 *
 * GETTERS
 * @property float dbtech_shop_currency
 * @property ArrayCollection dbtech_shop_purchase
 */
class User extends XFCP_User
{
	/**
	 * @param null $error
	 * @return bool
	 */
	public function canViewDbtechShopItems(&$error = null)
	{
		return $this->hasPermission('dbtech_shop', 'view');
	}

	/**
	 * @param null $error
	 * @return bool
	 */
	public function canPurchaseDbtechShopItems(&$error = null)
	{
		return $this->hasPermission('dbtech_shop', 'purchase');
	}

	/**
	 * @param null $error
	 * @return bool
	 */
	public function canUseDbtechShopBank(&$error = null)
	{
		return $this->hasPermission('dbtech_shop', 'bank');
	}

	/**
	 * @param null $error
	 * @return bool
	 */
	public function canUseDbtechShopSteal(&$error = null)
	{
		return $this->hasPermission('dbtech_shop', 'steal');
	}

	/**
	 * @param null $error
	 * @return bool
	 */
	public function canUseDbtechShopTrade(&$error = null)
	{
		return $this->hasPermission('dbtech_shop', 'trade');
	}

	/**
	 * @param null $error
	 * @return bool
	 */
	public function canViewDbtechShopLotteries(&$error = null)
	{
		return $this->hasPermission('dbtech_shop', 'viewLottery');
	}

	/**
	 * @param null $error
	 * @return bool
	 */
	public function canViewDbtechShopTradePosts(&$error = null)
	{
		return $this->hasPermission('dbtechShopTradePost', 'view');
	}

	/**
	 * @param null $error
	 * @return bool
	 */
	public function canAddDbtechShopItem(&$error = null)
	{
		return ($this->user_id && $this->hasPermission('dbtech_shop', 'add'));
	}

	/**
	 * @return bool
	 */
	public function canViewAnyDbtechShopTransaction()
	{
		return false;
		//		return ($this->canViewDbtechShopItems()
		//			&& $this->hasPermission('dbtechShop', 'viewAnyLog')
		//		);
	}

	/**
	 * @return bool
	 */
	public function canBypassDbtechShopCurrencyPrivacy()
	{
		return false;
		//		return ($this->canViewDbtechShop()
		//			&& $this->hasPermission('dbtechShop', 'bypassCurrencyPrivacy')
		//		);
	}

	/**
	 * @param $contentId
	 * @param $permission
	 *
	 * @return bool
	 */
	public function hasDbtechShopCategoryPermission($contentId, $permission)
	{
		return $this->PermissionSet->hasContentPermission('dbtech_shop_category', $contentId, $permission);
	}

	/**
	 * @param $contentId
	 * @param $permission
	 *
	 * @return bool
	 */
	public function hasDbtechShopItemPermission($contentId, $permission)
	{
		return $this->PermissionSet->hasContentPermission('dbtech_shop_item', $contentId, $permission);
	}

	/**
	 * @param array|null $categoryIds
	 */
	public function cacheDbtechShopCategoryPermissions(?array $categoryIds = null)
	{
		if (is_array($categoryIds))
		{
			\XF::permissionCache()->cacheContentPermsByIds($this->permission_combination_id, 'dbtech_shop_category', $categoryIds);
		}
		else
		{
			\XF::permissionCache()->cacheAllContentPerms($this->permission_combination_id, 'dbtech_shop_category');
		}
	}

	/**
	 * @param array|null $itemIds
	 */
	public function cacheDbtechShopItemPermissions(?array $itemIds = null)
	{
		if (is_array($itemIds))
		{
			\XF::permissionCache()->cacheContentPermsByIds($this->permission_combination_id, 'dbtech_shop_item', $itemIds);
		}
		else
		{
			\XF::permissionCache()->cacheAllContentPerms($this->permission_combination_id, 'dbtech_shop_item');
		}
	}

	/**
	 * @param Currency $currency
	 *
	 * @return mixed|null
	 */
	public function getDbtechShopCurrency(Currency $currency)
	{
		if (!$this->offsetExists($currency->column))
		{
			throw new \LogicException("Attempted to access column $currency->column on user, which did not exist.");
		}

		return $this->{$currency->column};
	}

	/**
	 * @return \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase>
	 * @throws PrintableException
	 */
	public function getDbtechShopPurchase()
	{
		if (!$this->user_id)
		{
			// Don't do it for guests, but allow this to be transformed into a member later
			return \XF::app()->em()->getEmptyCollection();
		}

		// Check the raw values, since normal getter will convert into an empty array, which is a valid value
		if (array_key_exists('dbtech_shop_purchase', $this->_newValues))
		{
			$purchases = $this->_newValues['dbtech_shop_purchase'];
		}
		else if (array_key_exists('dbtech_shop_purchase', $this->_values))
		{
			$purchases = $this->_values['dbtech_shop_purchase'];
		}
		else
		{
			$purchases = null;
		}

		if ($purchases === null)
		{
			// Raw value is null, so rebuild the cache and get the resulting array
			$purchaseRepo = \XF::app()->repository(PurchaseRepository::class);
			$purchases = $purchaseRepo->rebuildCacheForUser($this);
		}
		else
		{
			// Raw value is not null, so we have a valid value. Fetch the source decoded value (i.e. an array)
			$purchases = $this->dbtech_shop_purchase_;
		}

		$container = \XF::app()->container();

		if (!isset($container['dbtechShop.items']))
		{
			$container['dbtechShop.items'] = \XF::app()->finder(ItemFinder::class)
				->with('fullCategory')
				->fetch()
			;
		}

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Item> $items */
		$items = $container['dbtechShop.items'];

		//		$this->cacheDbtechShopCategoryPermissions();
		//		$this->cacheDbtechShopItemPermissions();

		$entities = [];
		foreach ($purchases AS $purchaseId => $purchase)
		{
			$purchaseEntity = \XF::app()->em()->instantiateEntity(Purchase::class, $purchase);

			// Add User entity to the Purchase entity
			$purchaseEntity->hydrateRelation('User', $this);

			if ($items !== null)
			{
				if (!$items->offsetExists($purchaseEntity->item_id))
				{
					$purchaseEntity->delete(false);
					continue;
				}

				// Add Item entity to the Purchase entity
				$purchaseEntity->hydrateRelation('Item', $items->offsetGet($purchaseEntity->item_id));
			}

			$entities[$purchaseId] = $purchaseEntity;
		}

		return \XF::app()->em()->getBasicCollection($entities);
	}

	/**
	 * @param $group
	 * @param $permission
	 *
	 * @return bool
	 */
	public function hasPermission($group, $permission)
	{
		$retval = parent::hasPermission($group, $permission);

		if (!$this->user_id)
		{
			// Don't do it for guests, but allow this to be transformed into a member later
			return $retval;
		}

		//		$this->cacheDbtechShopCategoryPermissions();
		//		$this->cacheDbtechShopItemPermissions();

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)
			->filterActivePurchasesForUser($this, false)
		;
		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('has_permission', [$group, $permission, &$retval]);
		}

		return $retval;
	}

	/**
	 * @param $contentType
	 * @param $contentId
	 * @param $permission
	 *
	 * @return bool
	 */
	public function hasContentPermission($contentType, $contentId, $permission)
	{
		$retval = parent::hasContentPermission($contentType, $contentId, $permission);

		if ($contentType == 'node')
		{
			/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
			$purchases = \XF::app()->repository(PurchaseRepository::class)
				->filterActivePurchasesForUser($this, false)
			;
			foreach ($purchases AS $purchase)
			{
				$handler = $purchase->handler;
				$handler->fire('has_node_permission', [$contentId, $permission, &$retval], $contentId);
			}
		}

		return $retval;
	}

	/**
	 * @param $contentId
	 * @param $permission
	 *
	 * @return bool
	 */
	public function hasNodePermission($contentId, $permission)
	{
		$retval = parent::hasNodePermission($contentId, $permission);

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)
			->filterActivePurchasesForUser($this, false)
		;
		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('has_node_permission', [$contentId, $permission, &$retval], $contentId);
		}

		return $retval;
	}

	/**
	 *
	 */
	protected function _preSave()
	{
		if ($this->isInsert() && !$this->isChanged('dbtech_shop_purchase'))
		{
			$this->dbtech_shop_purchase = [];
		}

		parent::_preSave();
	}

	/**
	 * @param bool $allowGetters
	 *
	 * @return array
	 */
	public function toArray($allowGetters = true)
	{
		$array = parent::toArray($allowGetters);

		if (!isset($array['dbtech_shop_purchase']))
		{
			return $array;
		}

		if ($array['dbtech_shop_purchase'] instanceof ArrayCollection)
		{
			$array['dbtech_shop_purchase'] = $this->dbtech_shop_purchase_;
		}

		return $array;
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->getters['dbtech_shop_currency'] = true;
		$structure->getters['dbtech_shop_purchase'] = true;

		$container = \XF::app()->container();
		if (isset($container['dbtechShop.currencies']) && $currencies = $container['dbtechShop.currencies'])
		{
			/** @var \DBTech\Shop\Entity\Currency[] $currencies */
			foreach ($currencies AS $currencyId => $currency)
			{
				// Add all currencies matching
				$structure->columns[$currency->column] = ['type' => Entity::FLOAT, 'default' => 0, 'changeLog' => false];
			}
		}

		$structure->columns['dbtech_shop_purchase'] = ['type' => Entity::JSON_ARRAY, 'changeLog' => false];
		$structure->columns['dbtech_shop_purchases'] = ['type' => Entity::UINT, 'default' => 0, 'changeLog' => false];
		$structure->columns['dbtech_shop_immunity'] = ['type' => Entity::UINT, 'default' => 0, 'changeLog' => false];
		$structure->columns['dbtech_shop_pendingtrades'] = ['type' => Entity::UINT, 'default' => 0, 'changeLog' => false];
		$structure->columns['dbtech_shop_item_count'] = ['type' => Entity::UINT, 'default' => 0, 'changeLog' => false];

		return $structure;
	}
}