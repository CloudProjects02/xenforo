<?php

namespace DBTech\Shop\Entity;

use DBTech\Shop\Finder\ItemFinder;
use DBTech\Shop\Finder\ItemPrefixFinder;
use DBTech\Shop\Finder\PurchaseFinder;
use DBTech\Shop\Repository\CategoryRepository;
use XF\Entity\AbstractCategoryTree;
use XF\Entity\Forum;
use XF\Entity\LinkableInterface;
use XF\Entity\User;
use XF\Entity\ViewableInterface;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * COLUMNS
 * @property int|null $category_id
 * @property string $title
 * @property string $description
 * @property int $item_count
 * @property int $last_update
 * @property string $last_item_title
 * @property string $last_item_title_
 * @property int $last_item_id
 * @property array $prefix_cache
 * @property array $field_cache
 * @property array $item_filters
 * @property bool $require_prefix
 * @property int $thread_node_id
 * @property int $thread_prefix_id
 * @property string $item_update_notify
 * @property bool $always_moderate_create
 * @property bool $always_moderate_update
 * @property int $min_tags
 * @property int $sales
 * @property array $sales_amounts
 * @property int $latest_customer_id
 * @property int $latest_sale_id
 * @property int $beneficiary
 * @property int $beneficiary_split
 * @property int $num_ratings
 * @property float $average_rating
 * @property int $positive_percent
 * @property int $negative_percent
 * @property int $neutral_percent
 * @property int $parent_category_id
 * @property int $display_order
 * @property int $lft
 * @property int $rgt
 * @property int $depth
 * @property array $breadcrumb_data
 *
 * GETTERS
 * @property-read AbstractCollection $prefixes
 *
 * RELATIONS
 * @property-read Forum|null $ThreadForum
 * @property-read User|null $Beneficiary
 * @property-read Item|null $LatestSale
 * @property-read User|null $LatestCustomer
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\CategoryWatch> $Watch
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\PermissionCacheContent> $Permissions
 */
class Category extends AbstractCategoryTree implements LinkableInterface, ViewableInterface
{
	protected array $_viewableDescendants = [];

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canView(&$error = null): bool
	{
		return $this->hasPermission('view');
	}

	/**
	 * @return bool
	 */
	public function canViewDeletedItems(): bool
	{
		return $this->hasPermission('viewDeleted');
	}

	/**
	 * @return bool
	 */
	public function canViewModeratedItems(): bool
	{
		return $this->hasPermission('viewModerated');
	}

	/**
	 * @param Item|null $item
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canEditTags(?Item $item = null, &$error = null): bool
	{
		if (!\XF::app()->options()->enableTagging)
		{
			return false;
		}

		$visitor = \XF::visitor();

		// if no item, assume will be owned by this person
		if (!$item || $item->user_id == $visitor->user_id)
		{
			if ($this->hasPermission('tagOwnItem'))
			{
				return true;
			}
		}

		return (
			$this->hasPermission('tagAnyItem')
			|| $this->hasPermission('manageAnyTag')
		);
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canUseInlineModeration(&$error = null): bool
	{
		return $this->hasPermission('inlineMod');
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canAddItem(&$error = null): bool
	{
		return \XF::visitor()->user_id && $this->hasPermission('add');
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canPurchase(&$error = null): bool
	{
		return \XF::visitor()->user_id && $this->hasPermission('purchase');
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canWatch(&$error = null): bool
	{
		return (bool) \XF::visitor()->user_id;
	}

	/**
	 * @param string $permission
	 *
	 * @return bool
	 */
	public function hasPermission(string $permission): bool
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		return $visitor->hasDbtechShopCategoryPermission($this->category_id, $permission);
	}

	/**
	 * @return mixed
	 */
	public function getViewableDescendants(): mixed
	{
		$userId = \XF::visitor()->user_id;
		if (!isset($this->_viewableDescendants[$userId]))
		{
			$categoryRepos = \XF::app()->repository(CategoryRepository::class);
			$viewable = $categoryRepos->getViewableCategories($this);
			$this->_viewableDescendants[$userId] = $viewable->toArray();
		}

		return $this->_viewableDescendants[$userId];
	}

	/**
	 * @param array $descendents
	 * @param null $userId
	 */
	public function cacheViewableDescendents(array $descendents, $userId = null): void
	{
		if ($userId === null)
		{
			$userId = \XF::visitor()->user_id;
		}

		$this->_viewableDescendants[$userId] = $descendents;
	}

	/**
	 * @param null $forcePrefix
	 *
	 * @return array
	 */
	public function getUsablePrefixes($forcePrefix = null): array
	{
		$prefixes = $this->prefixes;

		if ($forcePrefix instanceof ItemPrefix)
		{
			$forcePrefix = $forcePrefix->prefix_id;
		}

		$prefixes = $prefixes->filter(function ($prefix) use ($forcePrefix): bool
		{
			if ($forcePrefix && $forcePrefix == $prefix->prefix_id)
			{
				return true;
			}
			return $this->isPrefixUsable($prefix);
		});

		return $prefixes->groupBy('prefix_group_id');
	}

	/**
	 * @return array
	 */
	public function getPrefixesGrouped(): array
	{
		return $this->prefixes->groupBy('prefix_group_id');
	}

	/**
	 * @return AbstractCollection
	 */
	public function getPrefixes(): AbstractCollection
	{
		if (!$this->prefix_cache)
		{
			return \XF::app()->em()->getEmptyCollection();
		}

		return \XF::app()->finder(ItemPrefixFinder::class)
			->where('prefix_id', $this->prefix_cache)
			->order('materialized_order')
			->fetch()
		;
	}

	/**
	 * @param ItemPrefix|int $prefix
	 * @param User|null $user
	 *
	 * @return bool
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function isPrefixUsable($prefix, ?User $user = null): bool
	{
		if (!$this->isPrefixValid($prefix))
		{
			return false;
		}

		if (!($prefix instanceof ItemPrefix))
		{
			$prefix = \XF::app()->em()->find(ItemPrefix::class, $prefix);
			if (!$prefix)
			{
				return false;
			}
		}

		return $prefix->isUsableByUser($user);
	}

	/**
	 * @param ItemPrefix|int $prefix
	 *
	 * @return bool
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function isPrefixValid($prefix): bool
	{
		if ($prefix instanceof ItemPrefix)
		{
			$prefix = $prefix->prefix_id;
		}

		return (!$prefix || isset($this->prefix_cache[$prefix]));
	}

	/**
	 * @param string $itemType
	 *
	 * @return Item
	 * @throws \InvalidArgumentException
	 */
	public function getNewItem(string $itemType): Item
	{
		/** @var Item $item */
		$item = \XF::app()->em()->create(Item::class);
		$item->item_type_id = $itemType;
		$item->category_id = $this->category_id;
		$item->hydrateRelation('Category', $this);

		return $item;
	}

	/**
	 * @param Item|null $item
	 *
	 * @return string
	 */
	public function getNewContentState(?Item $item = null): string
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id && $this->hasPermission('approveUnapprove'))
		{
			return 'visible';
		}

		if (!$this->hasPermission('addWithoutApproval'))
		{
			return 'moderated';
		}

		if ($item)
		{
			return $this->always_moderate_update ? 'moderated' : 'visible';
		}

		return $this->always_moderate_create ? 'moderated' : 'visible';
	}

	/**
	 * @param bool $includeSelf
	 * @param string $linkType
	 *
	 * @return array
	 */
	public function getBreadcrumbs(bool $includeSelf = true, string $linkType = 'public'): array
	{
		$link = 'dbtech-shop/categories';
		return $this->_getBreadcrumbs($includeSelf, $linkType, $link);
	}

	/**
	 * @return array
	 */
	public function getCategoryListExtras(): array
	{
		return [
			'item_count' => $this->item_count,
			'last_update' => $this->last_update,
			'last_item_title' => $this->last_item_title,
			'last_item_id' => $this->last_item_id,
		];
	}

	/**
	 * @param Item $item
	 */
	public function itemAdded(Item $item): void
	{
		$this->item_count++;

		if ($item->last_update >= $this->last_update)
		{
			$this->last_update = $item->last_update;
			$this->last_item_title = $item->title;
			$this->last_item_id = $item->item_id;
		}
	}

	/**
	 * @param Item $item
	 *
	 * @throws \InvalidArgumentException
	 */
	public function itemDataChanged(Item $item): void
	{
		if ($item->isChanged(['last_update', 'title']))
		{
			if ($item->last_update >= $this->last_update)
			{
				$this->last_update = $item->last_update;
				$this->last_item_title = $item->title;
				$this->last_item_id = $item->item_id;
			}
			else if ($item->getExistingValue('last_update') == $this->last_update)
			{
				$this->rebuildLastItem();
			}
		}
	}

	/**
	 * @param Item $item
	 */
	public function itemRemoved(Item $item): void
	{
		$this->item_count--;

		if ($item->last_update == $this->last_update)
		{
			$this->rebuildLastItem();
		}
	}

	/**
	 * @return bool
	 */
	public function rebuildCounters(): bool
	{
		$this->rebuildItemCount();
		$this->rebuildLastItem();
		$this->rebuildLastSale();
		$this->rebuildRating();
		//		$this->rebuildSalesAmounts();

		return true;
	}

	/**
	 * @return int
	 */
	public function rebuildItemCount(): int
	{
		$this->item_count = (int) $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_dbtech_shop_item
			WHERE category_id = ?
				AND item_state = 'visible'
		", $this->category_id);

		return $this->item_count;
	}

	/**
	 *
	 */
	public function rebuildLastItem(): void
	{
		/** @var Item $item */
		$item = \XF::app()->finder(ItemFinder::class)
			->where('category_id', $this->category_id)
			->where('item_state', 'visible')
			->order('last_update', 'desc')
			->fetchOne();

		if ($item)
		{
			$this->last_update = $item->last_update;
			$this->last_item_title = $item->title;
			$this->last_item_id = $item->item_id;
		}
		else
		{
			$this->last_update = 0;
			$this->last_item_title = '';
			$this->last_item_id = 0;
		}
	}

	/**
	 *
	 */
	public function rebuildLastSale(): void
	{
		$finder = \XF::app()->finder(PurchaseFinder::class);

		$finder->where($finder->expression('
			item_id IN(SELECT item_id FROM xf_dbtech_shop_item WHERE category_id = ' . $this->category_id . ')
		'));

		/** @var Purchase $purchase */
		$purchase = $finder->order('dateline', 'desc')
			->fetchOne()
		;

		if ($purchase)
		{
			$this->latest_customer_id = $purchase->buyer_user_id;
			$this->latest_sale_id = $purchase->item_id;
		}
		else
		{
			$this->latest_customer_id = 0;
			$this->latest_sale_id = 0;
		}
	}

	/**
	 * @return bool
	 */
	public function rebuildRating(): bool
	{
		$ratings = $this->db()->fetchOne("
			SELECT COUNT(item_rating_id)
			FROM xf_dbtech_shop_item_rating AS rating
			LEFT JOIN xf_dbtech_shop_item AS item USING(item_id)
			WHERE item.category_id = ?
		", $this->category_id);

		$totalPositive = $this->db()->fetchOne("
			SELECT COUNT(item_rating_id)
			FROM xf_dbtech_shop_item_rating AS rating
			LEFT JOIN xf_dbtech_shop_item AS item USING(item_id)
			WHERE item.category_id = ?
				AND rating >= 4
		", $this->category_id);

		$totalNegative = $this->db()->fetchOne("
			SELECT COUNT(item_rating_id)
			FROM xf_dbtech_shop_item_rating AS rating
			LEFT JOIN xf_dbtech_shop_item AS item USING(item_id)
			WHERE item.category_id = ?
				AND rating <= 2
		", $this->category_id);

		$averageRating = $this->db()->fetchOne("
			SELECT AVG(rating)
			FROM xf_dbtech_shop_item_rating AS rating
			LEFT JOIN xf_dbtech_shop_item AS item USING(item_id)
			WHERE item.category_id = ?
				AND rating <= 2
		", $this->category_id);

		$this->num_ratings = $ratings;
		$this->average_rating = round($averageRating, 2);
		$this->positive_percent = $ratings ? round(($totalPositive / $ratings) * 100, 2) : 0;
		$this->negative_percent = $ratings ? round(($totalNegative / $ratings) * 100, 2) : 0;
		$this->neutral_percent = $ratings ? round((($ratings - $totalNegative - $totalPositive) / $ratings) * 100, 2) : 0;

		return true;
	}

	/**
	 * @param int $itemId
	 *
	 * @return bool
	 */
	protected function verifyItemId(int &$itemId): bool
	{
		return \XF::app()->em()->find(Item::class, $itemId) !== null;
	}

	/**
	 * @param int $userId
	 *
	 * @return bool
	 */
	protected function verifyUserIdOrZero(int &$userId): bool
	{
		if ($userId == 0)
		{
			return true;
		}

		return \XF::app()->em()->find(User::class, $userId) !== null;
	}

	/**
	 * @param int $userId
	 *
	 * @return bool
	 */
	protected function verifyBeneficiary(int &$userId): bool
	{
		if ($userId == -1 || $userId == 0)
		{
			// 0 or -1 is valid in this case
			return true;
		}

		return \XF::app()->em()->find(User::class, $userId) !== null;
	}

	/**
	 * @param bool $canonical
	 * @param array $extraParams
	 * @param null $hash
	 *
	 * @return string
	 */
	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null): string
	{
		$route = $canonical ? 'canonical:dbtech-shop/categories' : 'dbtech-shop/categories';
		return \XF::app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	/**
	 * @return string|null
	 */
	public function getContentPublicRoute(): ?string
	{
		return 'dbtech-shop/categories';
	}

	/**
	 * @param string $context
	 *
	 * @return Phrase
	 */
	public function getContentTitle(string $context = ''): Phrase
	{
		return \XF::phrase('dbtech_shop_category_x', ['title' => $this->title]);
	}

	/**
	 *
	 */
	protected function _preSave(): void
	{
		if ($this->isChanged(['thread_node_id', 'thread_prefix_id']))
		{
			if (!$this->thread_node_id)
			{
				$this->thread_prefix_id = 0;
			}
			else
			{
				if (!$this->ThreadForum)
				{
					$this->thread_node_id = 0;
					$this->thread_prefix_id = 0;
				}
				else if ($this->thread_prefix_id && !$this->ThreadForum->isPrefixValid($this->thread_prefix_id))
				{
					$this->thread_prefix_id = 0;
				}
			}
		}
	}

	/**
	 *
	 */
	protected function _postDelete(): void
	{
		if ($this->getOption('delete_items'))
		{
			\XF::app()->jobManager()->enqueueUnique('dbtechShopCategoryDelete' . $this->category_id, 'DBTech\Shop:CategoryDelete', [
				'category_id' => $this->category_id,
			]);
		}
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_category';
		$structure->shortName = 'DBTech\Shop:Category';
		$structure->primaryKey = 'category_id';
		$structure->contentType = 'dbtech_shop_category';
		$structure->columns = [
			'category_id'            => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'title'                  => [
				'type'      => self::STR,
				'maxLength' => 100,
				'required'  => 'please_enter_valid_title',
			],
			'description'            => ['type' => self::STR, 'default' => ''],
			'item_count'             => ['type' => self::UINT, 'default' => 0, 'forced' => true],
			'last_update'            => ['type' => self::UINT, 'default' => 0],
			'last_item_title'        => [
				'type'      => self::STR,
				'default'   => '',
				'maxLength' => 100,
				'censor'    => true,
			],
			'last_item_id'           => ['type' => self::UINT, 'default' => 0],
			'prefix_cache'           => ['type' => self::JSON_ARRAY, 'default' => []],
			'field_cache'            => ['type' => self::JSON_ARRAY, 'default' => []],
			'item_filters'           => ['type' => self::JSON_ARRAY, 'default' => []],
			'require_prefix'         => ['type' => self::BOOL, 'default' => false],
			'thread_node_id'         => ['type' => self::UINT, 'default' => 0],
			'thread_prefix_id'       => ['type' => self::UINT, 'default' => 0],
			'item_update_notify'     => [
				'type'          => self::STR,
				'default'       => 'thread',
				'allowedValues' => ['thread', 'reply'],
			],
			'always_moderate_create' => ['type' => self::BOOL, 'default' => false],
			'always_moderate_update' => ['type' => self::BOOL, 'default' => false],
			'min_tags'               => ['type' => self::UINT, 'forced' => true, 'default' => 0, 'max' => 100],
			'sales'                  => ['type' => self::UINT, 'default' => 0],
			'sales_amounts'          => ['type' => self::JSON_ARRAY, 'default' => []],
			'latest_customer_id'     => ['type' => self::UINT, 'verify' => 'verifyUserIdOrZero'],
			'latest_sale_id'         => ['type' => self::UINT, 'verify' => 'verifyItemId'],
			'beneficiary'            => ['type' => self::INT, 'default' => 0],
			'beneficiary_split'      => ['type' => self::UINT, 'default' => 100, 'min' => 0, 'max' => 100],
			'num_ratings'            => ['type' => self::INT, 'default' => 0],
			'average_rating'         => ['type' => self::FLOAT, 'default' => 0],
			'positive_percent'       => ['type' => self::UINT, 'default' => 0],
			'negative_percent'       => ['type' => self::UINT, 'default' => 0],
			'neutral_percent'        => ['type' => self::UINT, 'default' => 0],
		];
		$structure->behaviors = [];
		$structure->getters = [
			'prefixes' => true,
		];
		$structure->relations = [
			'ThreadForum' => [
				'entity' => Forum::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['node_id', '=', '$thread_node_id'],
				],
				'primary' => true,
				'with' => 'Node',
			],
			'Beneficiary' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$beneficiary'],
				],
				'primary' => true,
			],
			'LatestSale' => [
				'entity' => Item::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['item_id', '=', '$latest_sale_id'],
				],
				'primary' => true,
			],
			'LatestCustomer' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$latest_customer_id'],
				],
				'primary' => true,
			],
			'Watch' => [
				'entity' => CategoryWatch::class,
				'type' => self::TO_MANY,
				'conditions' => 'category_id',
				'key' => 'user_id',
			],
		];
		$structure->options = [
			'delete_items' => true,
		];

		static::addCategoryTreeStructureElements($structure);

		return $structure;
	}
}