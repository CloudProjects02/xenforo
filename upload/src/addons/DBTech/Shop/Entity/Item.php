<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Entity\LinkableInterface;
use XF\Entity\BookmarkTrait;
use XF\Entity\ReactionTrait;
use XF\BbCode\RenderableContentInterface;

/**
 * COLUMNS
 * @property int $item_id
 * @property string $title_
 * @property string $description
 * @property string $item_state
 * @property int $display_order
 * @property int $creation_date
 * @property int $last_update
 * @property array $user_criteria
 * @property int $category_id
 * @property array $code
 * @property string $item_type_id
 * @property int $thread_node_id
 * @property int $thread_prefix_id
 * @property int $discussion_thread_id
 * @property array $field_cache
 * @property array $item_fields_
 * @property array $item_filters
 * @property int $purchases
 * @property bool $display_in_list
 * @property bool $is_stealth_item
 * @property bool $auto_discard_expiry
 * @property array $item_flags
 * @property int $length_amount
 * @property string $length_unit
 * @property int $user_id
 * @property string $username
 * @property int $ip_id
 * @property int $warning_id
 * @property string $warning_message
 * @property bool $moderation
 * @property bool $purchasable_thread_creation
 * @property bool $purchasable_thread_page
 * @property int $currency_id
 * @property float $price
 * @property int $buyback_currency_id
 * @property float $buyback_price
 * @property int $buyback_time
 * @property bool $buyback_replenish
 * @property array $notifications
 * @property array $notifications_config
 * @property int $stock
 * @property int $maxstock
 * @property int $refill_time
 * @property int $last_refill_date
 * @property int $rating_count
 * @property int $rating_sum
 * @property float $rating_avg
 * @property float $rating_weighted
 * @property int $review_count
 * @property int $icon_date
 * @property int $prefix_id
 * @property array $tags
 * @property int $reaction_score
 * @property array $reactions_
 * @property array $reaction_users_
 *
 * GETTERS
 * @property string|\XF\Phrase $title
 * @property \XF\Phrase $tagline
 * @property \XF\Phrase $duration
 * @property \DBTech\Shop\ItemType\AbstractHandler|null $handler
 * @property int $real_review_count
 * @property \XF\Phrase $ItemTypeTitle
 * @property \XF\CustomField\Set $item_fields
 * @property array $item_rating_ids
 * @property mixed $reactions
 * @property mixed $reaction_users
 *
 * RELATIONS
 * @property \XF\Entity\Phrase $MasterTagline
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\PermissionCacheContent[] $Permissions
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\ItemRating[] $Ratings
 * @property \DBTech\Shop\Entity\Category $Category
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\Cart[] $Carts
 * @property \XF\Entity\User $User
 * @property \XF\Entity\Forum $ThreadForum
 * @property \XF\Entity\Thread $Discussion
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\Purchase[] $Purchases
 * @property \DBTech\Shop\Entity\Currency $PurchaseCurrency
 * @property \DBTech\Shop\Entity\Currency $BuybackCurrency
 * @property \DBTech\Shop\Entity\ItemPrefix $Prefix
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\ItemWatch[] $Watch
 * @property \XF\Entity\DeletionLog $DeletionLog
 * @property \XF\Entity\ApprovalQueue $ApprovalQueue
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\BookmarkItem[] $Bookmarks
 * @property \XF\Mvc\Entity\AbstractCollection|\XF\Entity\ReactionContent[] $Reactions
 */
class Item extends Entity implements LinkableInterface, RenderableContentInterface
{
	use BookmarkTrait;
	use ReactionTrait;

	/** @var int */
	public const RATING_WEIGHTED_THRESHOLD = 10;
	
	/** @var int */
	public const RATING_WEIGHTED_AVERAGE = 3;
	
	/** @var \DBTech\Shop\ItemType\AbstractHandler|null */
	protected $cachedHandler;
	
	
	/**
	 * @return string
	 */
	public function getTaglinePhraseName(): string
	{
		return 'dbtech_shop_item_tag.' . $this->item_id;
	}
	
	/**
	 * @return \XF\Phrase
	 */
	public function getTagline(): \XF\Phrase
	{
		return \XF::phrase($this->getTaglinePhraseName());
	}
	
	/**
	 * @return mixed|null
	 */
	public function getMasterTaglinePhrase(): ?\XF\Entity\Phrase
	{
		$phrase = $this->MasterTagline;
		if (!$phrase)
		{
			/** @var \XF\Entity\Phrase $phrase */
			$phrase = $this->_em->create('XF:Phrase');
			$phrase->title = $this->_getDeferredValue(function (): string
			{
				return $this->getTaglinePhraseName();
			}, 'save');
			$phrase->language_id = 0;
			$phrase->addon_id = '';
		}
		
		return $phrase;
	}
	
	public function getDuration(): \XF\Phrase
	{
		if ($this->length_amount == 0)
		{
			return \XF::phrase('permanent');
		}
		else
		{
			switch ($this->length_unit)
			{
				case 'day':
					return \XF::phrase('x_days', ['days' => $this->length_amount]);

				case 'month':
					return \XF::phrase('x_months', ['months' => $this->length_amount]);

				case 'year':
					return \XF::phrase('x_years', ['years' => $this->length_amount]);

				default:
					return \XF::phrase('n_a');
			}
		}
	}

	/**
	 * @return int
	 */
	public function getExpiryDate(): int
	{
		if ($this->length_amount == 0)
		{
			return 0;
		}
		else
		{
			switch ($this->length_unit)
			{
				case 'day':
					return \XF::$time + (60 * 60 * 24 * $this->length_amount);

				case 'month':
					return \XF::$time + (2629743 * $this->length_amount);

				case 'year':
					return \XF::$time + (31556926 * $this->length_amount);

				default:
					throw new \LogicException("Unknown length unit: {$this->length_unit}");
			}
		}
	}
	
	/**
	 * @return bool
	 */
	public function isIgnored(): bool
	{
		return \XF::visitor()->isIgnoring($this->user_id);
	}
	
	/**
	 * @return bool
	 */
	public function isVisible(): bool
	{
		return ($this->item_state == 'visible');
	}

	/**
	 * @return array
	 */
	protected function getDefaultFlags(): array
	{
		return [
			'is_giftable'          => false,
			'is_only_giftable'     => false,
			'send_gift_pm'         => true,
			'can_regift'           => true,
			'can_discard'          => true,
			'is_unique'            => true,
			'is_exclusive'         => false,
			'is_always_hidden'     => false,
			'can_reconfigure'      => true,
			'auto_discard'         => false,
			'enabled_custom_shops' => false,
		];
	}

	/**
	 * @param string $flag
	 * @param bool $fallback
	 *
	 * @return bool
	 */
	public function getFlag(string $flag, bool $fallback = true): bool
	{
		$flags = \array_replace($this->getDefaultFlags(), $this->item_flags);

		return $flags[$flag] ?? $fallback;
	}
	
	/**
	 * @return bool
	 */
	public function isDisplayed(): bool
	{
		return (!$this->getFlag('is_always_hidden')
			&& !$this->is_stealth_item
		);
	}
	
	/**
	 * @return bool
	 */
	public function isGiftable(): bool
	{
		return $this->getFlag('is_giftable');
	}
	
	/**
	 * @return bool
	 */
	public function isOnlyGiftable(): bool
	{
		return $this->getFlag('is_only_giftable');
	}
	
	/**
	 * @return bool
	 */
	public function isUnique(): bool
	{
		return $this->getFlag('is_unique');
	}
	
	/**
	 * @return bool
	 */
	public function isExclusive(): bool
	{
		return $this->getFlag('is_exclusive');
	}
	
	/**
	 * @return bool
	 */
	public function isAlwaysHidden(): bool
	{
		return $this->getFlag('is_always_hidden');
	}
	
	/**
	 * @return bool
	 */
	public function canReGift(): bool
	{
		return $this->getFlag('can_regift');
	}
	
	/**
	 * @return bool
	 */
	public function canSellBack(): bool
	{
		return ($this->buyback_currency_id
			&& $this->buyback_price
		);
	}

	/**
	 * @return bool
	 */
	public function canDiscard(): bool
	{
		return $this->getFlag('can_discard');
	}
	
	/**
	 * @return bool
	 */
	public function isConfigurable(): bool
	{
		return $this->handler->isConfigurable();
	}
	
	/**
	 * @return bool
	 */
	public function canReConfigure(): bool
	{
		return $this->getFlag('can_reconfigure');
	}
	
	/**
	 * @param string|null $sizeCode
	 * @return string
	 */
	public function getAbstractedIconPath(?string $sizeCode = null): string
	{
		$itemId = $this->item_id;
		
		return sprintf(
			'data://dbtechShop/itemIcons/%d/%d.jpg',
			floor($itemId / 1000),
			$itemId
		);
	}
	
	/**
	 * @param string|null $sizeCode
	 * @param bool $canonical
	 * @return mixed|null
	 */
	public function getIconUrl(?string $sizeCode = null, bool $canonical = false)
	{
		$app = $this->app();
		
		if ($this->icon_date)
		{
			$group = floor($this->item_id / 1000);
			return $app->applyExternalDataUrl(
				"dbtechShop/itemIcons/{$group}/{$this->item_id}.jpg?{$this->icon_date}",
				$canonical
			);
		}
		
		return null;
	}
	
	/**
	 * @param null $error
	 *
	 * @return mixed
	 */
	public function canView(&$error = null): bool
	{
		if (!$this->Category)
		{
			return false;
		}
		
		$visitor = \XF::visitor();
		
		if (!$this->hasPermission('view'))
		{
			return false;
		}
		
		if ($this->item_state == 'moderated')
		{
			if (
				(!$visitor->user_id || $visitor->user_id != $this->user_id)
				&& !$this->hasPermission('viewModerated')
			) {
				return false;
			}
		}
		elseif ($this->item_state == 'deleted')
		{
			if (!$this->hasPermission('viewDeleted'))
			{
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * @param null $error
	 * @return bool
	 */
	public function canEdit(&$error = null): bool
	{
		$visitor = \XF::visitor();
		
		if (!$visitor->user_id)
		{
			return false;
		}
		
		if ($this->hasPermission('editAny'))
		{
			return true;
		}
		
		return (
			$this->user_id == $visitor->user_id
			&& $this->hasPermission('updateOwn')
		);
	}
	
	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canEditIcon(&$error = null): bool
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}
		
		if ($this->hasPermission('editAny'))
		{
			return true;
		}
		
		if ($this->user_id == $visitor->user_id)
		{
			if ($this->hasPermission('updateOwn'))
			{
				return true;
			}
			
			if (!$this->icon_date && $this->creation_date > \XF::$time - 3 * 3600)
			{
				// allow an icon to be set shortly after item creation, even if not editable since you can't
				// specify an icon during creation
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canMove(&$error = null): bool
	{
		$visitor = \XF::visitor();
		
		return (
			$visitor->user_id
			&& $this->hasPermission('editAny')
		);
	}
	
	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canReassign(&$error = null): bool
	{
		$visitor = \XF::visitor();
		
		return (
			$visitor->user_id
			&& $this->hasPermission('reassign')
		);
	}
	
	/**
	 * @param null $error
	 *
	 * @return mixed|null
	 */
	public function canBookmarkContent(&$error = null): ?bool
	{
		return $this->isVisible();
	}
	
	/**
	 * @param string $type
	 * @param null $error
	 *
	 * @return bool|mixed
	 */
	public function canDelete(string $type = 'soft', &$error = null): bool
	{
		$visitor = \XF::visitor();
		
		if ($type != 'soft')
		{
			return $this->hasPermission('hardDeleteAny');
		}
		
		if ($this->hasPermission('deleteAny'))
		{
			return true;
		}
		
		return (
			$this->user_id == $visitor->user_id
			&& $this->hasPermission('deleteOwn')
		);
	}
	
	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canUndelete(&$error = null): bool
	{
		$visitor = \XF::visitor();
		return $visitor->user_id && $this->hasPermission('undelete');
	}
	
	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canApproveUnapprove(&$error = null): bool
	{
		return (
			\XF::visitor()->user_id
			&& $this->hasPermission('approveUnapprove')
		);
	}
	
	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canWatch(&$error = null): bool
	{
		$visitor = \XF::visitor();
		
		// don't let authors watch as only they can update anyway
		return (
			$visitor->user_id
			&& $visitor->user_id != $this->user_id
		);
	}
	
	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canEditTags(&$error = null): bool
	{
		$category = $this->Category;
		return $category ? $category->canEditTags($this, $error) : false;
	}
	
	/**
	 * @param null $error
	 * @return bool
	 */
	public function canReact(&$error = null): bool
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}
		
		if ($this->item_state != 'visible')
		{
			return false;
		}
		
		if ($this->user_id == $visitor->user_id)
		{
			$error = \XF::phraseDeferred('reacting_to_your_own_content_is_considered_cheating');
			return false;
		}
		
		return $this->hasPermission('react');
	}
	
	/**
	 * @return int
	 * @throws \InvalidArgumentException
	 */
	public function getRealReviewCount(): int
	{
		if (!$this->canViewDeletedContent())
		{
			return $this->review_count;
		}
		
		/** @var \DBTech\Shop\Repository\ItemRating $ratingRepo */
		$ratingRepo = $this->repository('DBTech\Shop:ItemRating');
		return $ratingRepo->findReviewsInItem($this)->total();
	}
	
	/**
	 * @return array
	 */
	public function getItemRatingIds(): array
	{
		return $this->db()->fetchAllColumn('
			SELECT item_rating_id
			FROM xf_dbtech_shop_item_rating
			WHERE item_id = ?
			ORDER BY rating_date
		', $this->item_id);
	}
	
	/**
	 * @return bool
	 */
	public function canPurchase(): bool
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}
		
//		if ($visitor->user_id == $this->user_id)
//		{
//			return false;
//		}
		
		if ($visitor->user_id != $this->user_id
			&& !$this->isVisible()
		) {
			return false;
		}
		
		if (!$this->hasPermission('purchase'))
		{
			return false;
		}
		
		if ($this->stock == 0)
		{
			return false;
		}
		
		return true;
	}
	
	/**
	 * @param bool $includeCart
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canPurchaseForSelf(bool $includeCart = true, &$error = null): bool
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		
		/** @var \XF\Mvc\Entity\ArrayCollection $purchases */
		$purchases = $visitor->dbtech_shop_purchase;
		
		if ($this->isUnique())
		{
			$itemIds = $purchases->pluckNamed('item_id', 'item_id');
			if (!empty($itemIds[$this->item_id]))
			{
				$error = \XF::phraseDeferred('dbtech_shop_this_item_is_unique_already_owned');
				return false;
			}
			
			if ($includeCart)
			{
				if (in_array($visitor->user_id, $this->Carts->pluckNamed('user_id')))
				{
					$error = \XF::phraseDeferred('dbtech_shop_this_item_is_unique_already_in_cart');
					return false;
				}
			}
		}
		
		if ($this->isExclusive())
		{
			$itemTypeIds = $purchases->pluck(function (Purchase $e, int $k): array
			{
				return [$e->Item->item_type_id, $e->Item->item_type_id];
			});
			if (!empty($itemTypeIds[$this->item_type_id]))
			{
				$error = \XF::phraseDeferred('dbtech_shop_this_item_is_exclusive_already_owned', [
					'itemType' => $this->getItemTypeTitle()
				]);
				return false;
			}
			
			if ($includeCart)
			{
				/** @var Cart[]|\XF\Mvc\Entity\ArrayCollection $carts */
				$carts = $this->Carts;
				$cartItemTypeIds = $carts->pluck(function (Cart $e, $k) use ($visitor): ?array
				{
					if ($e->user_id != $visitor->user_id)
					{
						return null;
					}
					
					return [$e->Item->item_type_id, $e->Item->item_type_id];
				});
				if (!empty($cartItemTypeIds[$this->item_type_id]))
				{
					$error = \XF::phraseDeferred('dbtech_shop_this_item_is_exclusive_already_in_cart', [
						'itemType' => $this->getItemTypeTitle()
					]);
					return false;
				}
			}
		}
		
		return true;
	}
	
	
	/**
	 * @param \XF\Entity\User $user
	 * @param bool $includeCart
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canPurchaseForUser(\XF\Entity\User $user, bool $includeCart = true, &$error = null): bool
	{
		/** @var \XF\Mvc\Entity\ArrayCollection $purchases */
		$purchases = $user->dbtech_shop_purchase;
		
		if ($this->isUnique())
		{
			$itemIds = $purchases->pluckNamed('item_id', 'item_id');
			if (!empty($itemIds[$this->item_id]))
			{
				$error = \XF::phraseDeferred('dbtech_shop_unique_item_gifterror', [
					'recipient' => $user->username,
					'item' => $this->title
				]);
				return false;
			}
			
			if ($includeCart)
			{
				if (in_array($user->user_id, $this->Carts->pluckNamed('user_id')))
				{
					$error = \XF::phraseDeferred('dbtech_shop_this_item_is_unique_recipient_already_in_cart');
					return false;
				}
			}
		}
		
		if ($this->isExclusive())
		{
			$itemTypeIds = $purchases->pluck(function (Purchase $e, int $k): array
			{
				return [$e->Item->item_type_id, $e->Item->item_type_id];
			});
			if (!empty($itemTypeIds[$this->item_type_id]))
			{
				$error = \XF::phraseDeferred('dbtech_shop_exclusive_item_gifterror', [
					'recipient' => $user->username,
					'itemType' => $this->getItemTypeTitle()
				]);
				return false;
			}
			
			if ($includeCart)
			{
				/** @var Cart[]|\XF\Mvc\Entity\ArrayCollection $carts */
				$carts = $this->Carts;
				$cartItemTypeIds = $carts->pluck(function (Cart $e, int $k) use ($user): ?array
				{
					if ($e->user_id != $user->user_id)
					{
						return null;
					}
					
					return [$e->Item->item_type_id, $e->Item->item_type_id];
				});
				if (!empty($cartItemTypeIds[$this->item_type_id]))
				{
					$error = \XF::phraseDeferred('dbtech_shop_this_item_is_exclusive_recipient_already_in_cart', [
						'itemType' => $this->getItemTypeTitle()
					]);
					return false;
				}
			}
		}
		
		return true;
	}
	
	/**
	 * @return mixed
	 */
	public function canViewDeletedContent(): bool
	{
		return $this->hasPermission('viewDeleted');
	}
	
	/**
	 * @return bool
	 */
	public function canViewModeratedContent(): bool
	{
		$visitor = \XF::visitor();
		if ($this->hasPermission('viewModerated'))
		{
			return true;
		}
		
		return $visitor->user_id && $this->user_id == $visitor->user_id;
	}
	
	/**
	 * @param bool $checkPurchaseIfRequired
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canRate(bool $checkPurchaseIfRequired = true, &$error = null): bool
	{
		if (!$this->app()->options()->dbtechShopEnableRate)
		{
			return false;
		}
		
		if (!$this->isVisible())
		{
			return false;
		}
		
		$visitor = \XF::visitor();
		if (!$visitor->user_id || $visitor->user_id == $this->user_id)
		{
			return false;
		}
		
		if (!$this->hasPermission('rate') || !$this->hasPermission('purchase'))
		{
			// if you can't buy, you can't rate it
			return false;
		}
		
		if (
			$checkPurchaseIfRequired
			&& $this->app()->options()->dbtechShopRequirePurchaseToRate
			&& !$this->Purchases[$visitor->user_id]
		) {
			$error = \XF::phraseDeferred('dbtech_shop_you_only_rate_item_purchased');
			return false;
		}
		
		return true;
	}
	
	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canUseInlineModeration(&$error = null): bool
	{
		$visitor = \XF::visitor();
		return ($visitor->user_id && $this->hasPermission('inlineMod'));
	}
	
	/**
	 * @return bool
	 */
	public function canSendModeratorActionAlert(): bool
	{
		$visitor = \XF::visitor();
		
		return (
			$visitor->user_id
			&& $this->user_id
			&& $visitor->user_id != $this->user_id
			&& $this->item_state == 'visible'
		);
	}
	
	/**
	 * @param null $error
	 * @param \XF\Entity\User|null $asUser
	 *
	 * @return bool
	 */
	public function canReport(&$error = null, ?\XF\Entity\User $asUser = null): bool
	{
		$asUser = $asUser ?: \XF::visitor();
		return $asUser->canReport($error);
	}
	
	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canWarn(&$error = null): bool
	{
		$visitor = \XF::visitor();
		
		if ($this->warning_id
			|| !$this->user_id
			|| !$visitor->user_id
			|| $this->user_id == $visitor->user_id
			|| !$this->hasPermission('warn')
		) {
			return false;
		}
		
		$user = $this->User;
		return ($user && $user->isWarnable());
	}
	
	/**
	 * @param string $permission
	 * @return bool
	 */
	public function hasPermission(string $permission): bool
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		
		switch ($permission)
		{
			case 'view':
			case 'purchase':
			case 'react':
			case 'rate':
				// These are per-item
				return $visitor->hasDbtechShopItemPermission($this->item_id, $permission);

			default:
				// The rest are per-category
				return $visitor->hasDbtechShopCategoryPermission($this->category_id, $permission);
		}
	}

	/**
	 * @return \DBTech\Shop\ItemType\AbstractHandler|null
	 * @throws \Exception
	 */
	public function getHandler(): ?\DBTech\Shop\ItemType\AbstractHandler
	{
		if (!$this->cachedHandler)
		{
			$this->cachedHandler = $this->getItemRepo()
				->getHandler($this->item_type_id)
			;
		}

		if ($this->cachedHandler !== null)
		{
			$handler = clone $this->cachedHandler;

			$handler->setItem($this);
			return $handler;
		}

		return null;
	}
	
	/**
	 * @return bool
	 */
	public function isAdminConfigurable(): bool
	{
		return !empty($this->handler->getAdminConfigTemplate());
	}
	
	/**
	 * @return bool
	 */
	public function isUserConfigurable(): bool
	{
		return ($this->isConfigurable()
			&& !empty($this->handler->getUserConfigTemplate())
		);
	}
	
	/**
	 * @return string|\XF\Phrase
	 */
	public function getTitle()
	{
		switch ($this->item_type_id)
		{
			default:
				if ($this->title_)
				{
					return $this->title_;
				}
				
				return $this->getItemTypeTitle();
		}
	}
	
	/**
	 * @return \XF\Phrase
	 */
	public function getItemTypeTitle(): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_itemtype_title.' . $this->item_type_id);
	}
	
	/**
	 * @return \XF\Phrase
	 */
	public function getItemTypeDescription(): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_itemtype_description.' . $this->item_type_id);
	}
	
	/**
	 * @return bool
	 */
	public function hasViewableDiscussion(): bool
	{
		if (!$this->discussion_thread_id)
		{
			return false;
		}
		
		$thread = $this->Discussion;
		if (!$thread)
		{
			return false;
		}
		
		return $thread->canView();
	}
	
	/**
	 * @return array
	 */
	public function getExtraFieldTabs(): array
	{
		if (!$this->getValue('item_fields'))
		{
			// if they haven't set anything, we can bail out quickly
			return [];
		}
		
		/** @var \XF\CustomField\Set $fieldSet */
		$fieldSet = $this->item_fields;
		$definitionSet = $fieldSet->getDefinitionSet()
			->filterOnly($this->Category->field_cache)
			->filterGroup('new_tab')
			->filterWithValue($fieldSet);
		
		$output = [];
		foreach ($definitionSet AS $fieldId => $definition)
		{
			$output[$fieldId] = $definition->title;
		}
		
		return $output;
	}
	
	/**
	 * @return string
	 */
	public function getFieldEditMode(): string
	{
		$visitor = \XF::visitor();
		
		$isSelf = ($visitor->user_id == $this->user_id || !$this->item_id);
		$isMod = ($visitor->user_id && $this->hasPermission('editAny'));
		
		if ($isMod || !$isSelf)
		{
			return $isSelf ? 'moderator_user' : 'moderator';
		}
		else
		{
			return 'user';
		}
	}

	/**
	 * @return \XF\CustomField\Set
	 * @throws \Exception
	 */
	public function getItemFields(): \XF\CustomField\Set
	{
		$class = 'XF\CustomField\Set';
		$class = $this->app()->extendClass($class);

		/** @var \XF\CustomField\DefinitionSet $fieldDefinitions */
		$fieldDefinitions = $this->app()->container('customFields.dbtechShopItems');

		return new $class($fieldDefinitions, $this, 'item_fields');
	}
	
	/**
	 * @return string
	 */
	public function getExpectedThreadTitle(): string
	{
		$template = '';
		$options = $this->app()->options();
		
		if ($this->item_state != 'visible' && $options->dbtechShopContentDeleteThreadAction['update_title'])
		{
			$template = $options->dbtechShopContentDeleteThreadAction['title_template'];
		}
		
		if (!$template)
		{
			$template = '{title} [{category}]';
		}
		
		$threadTitle = strtr($template, [
			'{title}' => $this->title,
			'{category}' => $this->Category->title,
		]);
		return $this->app()->stringFormatter()->wholeWordTrim($threadTitle, 100);
	}
	
	/**
	 * @param string $context
	 * @param string $type
	 *
	 * @return array
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function getBbCodeRenderOptions($context, $type): array
	{
		return [
			'entity' => $this,
			'user' => $this->User,
			'attachments' => [],
			'viewAttachments' => false
		];
	}
	
	/**
	 * @param bool $includeSelf
	 *
	 * @return array
	 */
	public function getBreadcrumbs(bool $includeSelf = true): array
	{
		$breadcrumbs = $this->Category ? $this->Category->getBreadcrumbs() : [];
		if ($includeSelf && $this->exists())
		{
			$breadcrumbs[] = [
				'href' => $this->app()->router()->buildLink('dbtech-shop', $this),
				'value' => $this->title
			];
		}
		
		return $breadcrumbs;
	}
	
	/**
	 * @return bool
	 */
	public function rebuildCounters(): bool
	{
		// I'm sure I'll have uses for this, then switch this to true
//		$this->rebuildReviewCount();
//		$this->rebuildRating();
		return false;
	}
	
	/**
	 * @return int
	 */
	public function rebuildReviewCount(): int
	{
		$this->review_count = (int)$this->db()->fetchOne("
			SELECT COUNT(item_rating_id)
				FROM xf_dbtech_shop_item_rating
				WHERE item_id = ?
					AND is_review = 1
					AND rating_state = 'visible'
		", $this->item_id);
		
		return $this->review_count;
	}
	
	/**
	 *
	 */
	public function rebuildRating()
	{
		$rating = $this->db()->fetchRow("
			SELECT COUNT(item_rating_id) AS total,
				SUM(rating) AS sum
			FROM xf_dbtech_shop_item_rating
			WHERE item_id = ?
				AND count_rating = 1
				AND rating_state = 'visible'
		", $this->item_id);
		
		$this->rating_sum = $rating['sum'] ?: 0;
		$this->rating_count = $rating['total'] ?: 0;
		
		if ($this->Category)
		{
			$this->Category->rebuildRating();
			$this->Category->saveIfChanged();
		}
	}
	
	/**
	 *
	 */
	protected function updateRatingAverage()
	{
		$threshold = self::RATING_WEIGHTED_THRESHOLD;
		$average = self::RATING_WEIGHTED_AVERAGE;
		
		$this->rating_weighted = ($threshold * $average + $this->rating_sum) / ($threshold + $this->rating_count);
		
		if ($this->rating_count)
		{
			$this->rating_avg = $this->rating_sum / $this->rating_count;
		}
		else
		{
			$this->rating_avg = 0;
		}
	}
	
	/**
	 *
	 */
	public function rebuildItemFieldValuesCache()
	{
		$this->repository('DBTech\Shop:ItemField')->rebuildItemFieldValuesCache($this->item_id);
	}
	
	/**
	 * @param array $criteria
	 *
	 * @return bool
	 */
	protected function verifyUserCriteria(array &$criteria): bool
	{
		$userCriteria = \XF::app()->criteria('XF:User', $criteria);
		$criteria = $userCriteria->getCriteria();

		return true;
	}
	
	/**
	 * @param int $categoryId
	 *
	 * @return bool
	 */
	protected function verifyCategoryId(int &$categoryId): bool
	{
		return $this->_em->find('DBTech\Shop:Category', $categoryId) !== null;
	}
	
	/**
	 * @param int $currencyId
	 *
	 * @return bool
	 */
	protected function verifyCurrencyId(int &$currencyId): bool
	{
		return $this->_em->find('DBTech\Shop:Currency', $currencyId) !== null;
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
		
		return $this->_em->find('XF:User', $userId) !== null;
	}
	
	/**
	 * @param int $amount
	 * @param null $userId
	 *
	 * @throws \XF\Db\Exception
	 */
	protected function adjustUserItemCountIfNeeded(int $amount, $userId = null)
	{
		if ($userId === null)
		{
			$userId = $this->user_id;
		}
		
		if ($userId)
		{
			$this->db()->query('
				UPDATE xf_user
				SET dbtech_shop_item_count = GREATEST(0, CAST(dbtech_shop_item_count AS SIGNED) + ?)
				WHERE user_id = ?
			', [$amount, $userId]);
		}
	}
	
	/**
	 * @throws \XF\Db\Exception
	 */
	protected function itemMadeVisible()
	{
		$this->adjustUserItemCountIfNeeded(1);
		
		if ($this->discussion_thread_id && $this->Discussion && $this->Discussion->discussion_type == 'dbtech_shop_item')
		{
			$thread = $this->Discussion;
			
			switch ($this->app()->options()->dbtechShopContentDeleteThreadAction['action'])
			{
				case 'delete':
					$thread->discussion_state = 'visible';
					break;
				
				case 'close':
					$thread->discussion_open = true;
					break;
			}
			
			$thread->title = $this->getExpectedThreadTitle();
			$thread->saveIfChanged($saved, false, false);
		}
		
		/** @var \XF\Repository\Reaction $reactionRepo */
		$reactionRepo = $this->repository('XF:Reaction');
		$reactionRepo->recalculateReactionIsCounted('dbtech_shop_item', $this->item_id);
	}
	
	/**
	 * @param bool $hardDelete
	 *
	 * @throws \XF\Db\Exception
	 */
	protected function itemHidden(bool $hardDelete = false)
	{
		$this->adjustUserItemCountIfNeeded(-1);
		
		if ($this->discussion_thread_id && $this->Discussion && $this->Discussion->discussion_type == 'dbtech_shop_item')
		{
			$thread = $this->Discussion;
			
			switch ($this->app()->options()->dbtechShopContentDeleteThreadAction['action'])
			{
				case 'delete':
					$thread->discussion_state = 'deleted';
					break;
				
				case 'close':
					$thread->discussion_open = false;
					break;
			}
			
			$thread->title = $this->getExpectedThreadTitle();
			$thread->saveIfChanged($saved, false, false);
		}
		
		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->fastDeleteAlertsForContent('dbtech_shop_rating', $this->item_rating_ids);
	}
	
	/**
	 * @param Category $from
	 * @param Category $to
	 */
	protected function itemMoved(Category $from, Category $to)
	{
	}
	
	/**
	 *
	 * @throws \InvalidArgumentException
	 * @throws \XF\Db\Exception
	 */
	protected function itemReassigned()
	{
		if ($this->item_state == 'visible')
		{
			$this->adjustUserItemCountIfNeeded(-1, $this->getExistingValue('user_id'));
			$this->adjustUserItemCountIfNeeded(1);
		}
	}
	
	/**
	 * @throws \XF\Db\Exception
	 */
	protected function itemInsertedVisible()
	{
		$this->adjustUserItemCountIfNeeded(1);
	}
	
	/**
	 *
	 */
	protected function submitHamData()
	{
		/** @var \XF\Spam\ContentChecker $submitter */
		$submitter = $this->app()->container('spam.contentHamSubmitter');
		$submitter->submitHam('dbtech_shop_item', $this->item_id);
	}
	
	/**
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	protected function updateCategoryRecord()
	{
		if (!$this->Category)
		{
			return;
		}
		
		$category = $this->Category;
		
		if ($this->isUpdate() && $this->isChanged('category_id'))
		{
			// moved, trumps the rest
			if ($this->item_state == 'visible')
			{
				$category->itemAdded($this);
				$category->save();
			}
			
			if ($this->getExistingValue('item_state') == 'visible')
			{
				/** @var Category $oldCategory */
				$oldCategory = $this->getExistingRelation('Category');
				if ($oldCategory)
				{
					$oldCategory->itemRemoved($this);
					$oldCategory->save();
				}
			}
			
			return;
		}
		
		// check for entering/leaving visible
		$visibilityChange = $this->isStateChanged('item_state', 'visible');
		if ($visibilityChange == 'enter')
		{
			$category->itemAdded($this);
			$category->save();
		}
		elseif ($visibilityChange == 'leave')
		{
			$category->itemRemoved($this);
			$category->save();
		}
		elseif ($this->isUpdate() && $this->item_state == 'visible')
		{
			$category->itemDataChanged($this);
			$category->save();
		}
	}
	
	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if ($this->prefix_id && $this->isChanged(['prefix_id', 'category_id']) && !$this->Category->isPrefixValid($this->prefix_id))
		{
			$this->prefix_id = 0;
		}
		
		if (!$this->user_id && !$this->getOption('is_automated'))
		{
			/** @var \XF\Entity\User $user */
			if (
				$this->app()->options()->dbtechShopDefaultItemOwner
				&& $user = $this->_em->find('XF:User', $this->app()->options()->dbtechShopDefaultItemOwner)
			) {
				$this->user_id = $user->user_id;
				$this->username = $user->username;
			}
			else
			{
				$visitor = \XF::visitor();
				$this->user_id = $visitor->user_id;
				$this->username = $visitor->username;
			}
		}
		
		if ($this->isInsert() || $this->isChanged(['rating_sum', 'rating_count']))
		{
			$this->updateRatingAverage();
		}
		
		if ($this->buyback_price > $this->price
			&& $this->buyback_currency_id == $this->currency_id
		) {
			// Ensure people aren't nawty
			$this->_setInternal('buyback_price', $this->price);
		}
		
		if ($this->getOption('admin_edit'))
		{
			if ($this->stock == -1)
			{
				// Override maxstock
				$this->_setInternal('maxstock', -1);
			}
			else
			{
				if ($this->maxstock < $this->stock)
				{
					if (!is_numeric($this->stock))
					{
						// The remaining stock wasn't a numeric value, just set both of them to 0
						$this->_setInternal('stock', 0);
						$this->_setInternal('maxstock', 0);
					}
					else
					{
						// Override maxstock
						$this->_setInternal('maxstock', $this->stock);
					}
				}
			}
		}
	}
	
	/**
	 * @throws \Exception
	 */
	protected function _postSave()
	{
		$visibilityChange = $this->isStateChanged('item_state', 'visible');
		$approvalChange = $this->isStateChanged('item_state', 'moderated');
		$deletionChange = $this->isStateChanged('item_state', 'deleted');
		
		if ($this->isUpdate())
		{
			if ($visibilityChange == 'enter')
			{
				$this->itemMadeVisible();
				
				if ($approvalChange)
				{
					$this->submitHamData();
				}
			}
			elseif ($visibilityChange == 'leave')
			{
				$this->itemHidden();
			}
			
			if ($this->isChanged('category_id'))
			{
				/** @var \DBTech\Shop\Entity\Category $oldCategory */
				$oldCategory = $this->getExistingRelation('Category');
				if ($oldCategory && $this->Category)
				{
					$this->itemMoved($oldCategory, $this->Category);
				}
			}
			
			if ($deletionChange == 'leave' && $this->DeletionLog)
			{
				$this->DeletionLog->delete();
			}
			
			if ($deletionChange == 'leave' && $this->Discussion)
			{
				$this->Discussion->discussion_state = 'visible';
				$this->Discussion->save();
			}
			
			if ($approvalChange == 'leave' && $this->ApprovalQueue)
			{
				$this->ApprovalQueue->delete();
			}
		}
		else
		{
			// insert
			if ($this->item_state == 'visible')
			{
				$this->itemInsertedVisible();
			}
		}
		
		if ($this->isUpdate())
		{
			if ($this->isChanged('user_id'))
			{
				$this->itemReassigned();
			}
		}
		
		if ($this->discussion_thread_id)
		{
			$newThreadTitle = $this->getExpectedThreadTitle();
			if (
				$this->Discussion
				&& $this->Discussion->discussion_type == 'dbtech_shop_item'
				&& $newThreadTitle != $this->Discussion->title
			) {
				$this->Discussion->title = $newThreadTitle;
				$this->Discussion->saveIfChanged($saved, false, false);
			}
		}
		
		if ($approvalChange == 'enter')
		{
			/** @var \XF\Entity\ApprovalQueue $approvalQueue */
			$approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
			$approvalQueue->content_date = $this->creation_date;
			$approvalQueue->save();
		}
		elseif ($deletionChange == 'enter' && !$this->DeletionLog)
		{
			$delLog = $this->getRelationOrDefault('DeletionLog', false);
			$delLog->setFromVisitor();
			$delLog->save();
		}
		
		$this->updateCategoryRecord();
		
		if ($this->isUpdate() && $this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorChanges('dbtech_shop_item', $this);
		}
		
		if ($this->item_type_id == 'usernamestyle2')
		{
			$this->rebuildUserNameStyleCache();
		}
		
		if ($this->item_type_id == 'usertitlestyle2')
		{
			$this->rebuildUserTitleStyleCache();
		}
	}
	
	/**
	 * @throws \Exception
	 */
	protected function _postDelete()
	{
		if ($this->item_state == 'visible')
		{
			$this->itemHidden(true);
		}
		
		if ($this->Category && $this->item_state == 'visible')
		{
			$this->Category->itemRemoved($this);
			$this->Category->save();
		}
		
		if ($this->item_state == 'deleted' && $this->DeletionLog)
		{
			$this->DeletionLog->delete();
		}
		
		if ($this->item_state == 'moderated' && $this->ApprovalQueue)
		{
			$this->ApprovalQueue->delete();
		}
		
		if ($this->getOption('log_moderator'))
		{
			$this->app()->logger()->logModeratorAction('dbtech_shop_item', $this, 'delete_hard');
		}
		
		$db = $this->db();
		
		//$db->delete('xf_dbtech_shop_item_feature', 'item_id = ?', $this->item_id);
		$db->delete('xf_dbtech_shop_item_watch', 'item_id = ?', $this->item_id);
		
		$this->app()->jobManager()->enqueue('DBTech\Shop:ItemDeleteCleanUp', [
			'itemId' => $this->item_id,
			'title' => $this->title
		]);
		
		/** @var \XF\Repository\Reaction $reactionRepo */
		$reactionRepo = $this->repository('XF:Reaction');
		$reactionRepo->fastDeleteReactions('dbtech_shop_item', $this->item_id);
		
		/** @var \DBTech\Shop\Service\Item\Icon $iconService */
		$iconService = $this->app()->service('DBTech\Shop:Item\Icon', $this);
		$iconService->deleteIconForItemDelete();
		
		/** @var \DBTech\Shop\Entity\Purchase[] $purchases */
		$purchases = $this->finder('DBTech\Shop:Purchase')
			->where('item_id', $this->item_id)
			->fetch()
		;
		
		foreach ($purchases as $purchase)
		{
			$purchase->hydrateRelation('Item', $this);
			
			$this->getHandler()
				->setPurchase($purchase)
				->logIp(false)
				->discard($null, 'item_delete')
			;
		}
		
		$db = $this->db();
		
		// Update other records - delete purchases again just in case some failed to discard
		$db->update('xf_dbtech_shop_category', ['latest_sale_id' => 0], 'latest_sale_id = ?', $this->item_id);
		$db->delete('xf_dbtech_shop_purchase', 'item_id = ?', $this->item_id);
		
		if ($this->item_type_id == 'usernamestyle2')
		{
			$this->rebuildUserNameStyleCache();
		}
		
		if ($this->item_type_id == 'usertitlestyle2')
		{
			$this->rebuildUserTitleStyleCache();
		}
	}
	
	/**
	 * @param string $reason
	 * @param \XF\Entity\User|null $byUser
	 *
	 * @return bool
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function softDelete(string $reason = '', ?\XF\Entity\User $byUser = null): bool
	{
		$byUser = $byUser ?: \XF::visitor();
		
		if ($this->item_state == 'deleted')
		{
			return false;
		}
		
		$this->item_state = 'deleted';
		
		/** @var \XF\Entity\DeletionLog $deletionLog */
		$deletionLog = $this->getRelationOrDefault('DeletionLog');
		$deletionLog->setFromUser($byUser);
		$deletionLog->delete_reason = $reason;
		
		$this->save();
		
		return true;
	}

	protected function handleUsergroupDiscounts(array $discountData)
	{
		// Delete existing discounts
		$this->db()->delete('xf_dbtech_shop_item_usergroup_discount', 'item_id = ?', $this->item_id);
		
		// Add new discounts
		foreach ($discountData as $userGroupId => $discount)
		{
			$userGroupId = intval($userGroupId);
			$discountType = $discount['type'] ?? 'percentage';
			$discountValue = floatval($discount['value'] ?? 0);
			
			if ($userGroupId > 0 && $discountValue > 0)
			{
				/** @var \DBTech\Shop\Entity\ItemUsergroupDiscount $discountEntity */
				$discountEntity = $this->em()->create('DBTech\Shop:ItemUsergroupDiscount');
				$discountEntity->bulkSet([
					'item_id' => $this->item_id,
					'user_group_id' => $userGroupId,
					'discount_type' => $discountType,
					'discount_value' => $discountValue
				]);
				$discountEntity->save();
			}
		}
	}
	
	/**
	 * @param \XF\Entity\User|null $byUser
	 *
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function unDelete(?\XF\Entity\User $byUser = null): bool
	{
		$byUser = $byUser ?: \XF::visitor();
		
		if ($this->item_state == 'visible')
		{
			return false;
		}
		
		$this->item_state = 'visible';
		$this->save();
		
		return true;
	}
	
	/**
	 *
	 */
	protected function rebuildUserNameStyleCache()
	{
		$repo = $this->getPurchaseRepo();
		
		\XF::runOnce('dbtShopUserNameStyleRebuild', function () use ($repo)
		{
			$repo->rebuildUserNameStyleCache();
		});
	}
	
	/**
	 *
	 */
	protected function rebuildUserTitleStyleCache()
	{
		$repo = $this->getPurchaseRepo();
		
		\XF::runOnce('dbtShopUserTitleStyleRebuild', function () use ($repo)
		{
			$repo->rebuildUserTitleStyleCache();
		});
	}

	/**
	 * @param bool $canonical
	 * @param array $extraParams
	 * @param string|null $hash
	 *
	 * @return mixed|string
	 */
	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null): string
	{
		$route = $canonical ? 'canonical:dbtech-shop' : 'dbtech-shop';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	/**
	 * @return string|null
	 */
	public function getContentPublicRoute(): ?string
	{
		return 'dbtech-shop';
	}

	/**
	 * @param string $context
	 *
	 * @return string|\XF\Phrase
	 */
	public function getContentTitle(string $context = '')
	{
		return \XF::phrase('dbtech_shop_item_x', ['title' => $this->title]);
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_item';
		$structure->shortName = 'DBTech\Shop:Item';
		$structure->contentType = 'dbtech_shop_item';
		$structure->primaryKey = 'item_id';
		$structure->columns = [
			'item_id'                     => ['type' => self::UINT, 'autoIncrement' => true],
			'title'                       => ['type' => self::STR, 'required' => true, 'maxLength' => 50],
			'description'                 => ['type' => self::STR, 'default' => ''],
			'item_state'                  => [
				'type'          => self::STR,
				'default'       => 'visible',
				'allowedValues' => [
					'visible',
					'moderated',
					'deleted'
				]
			],
			'display_order'               => ['type' => self::UINT, 'default' => 10],
			'creation_date'               => ['type' => self::UINT, 'default' => \XF::$time],
			'last_update'                 => ['type' => self::UINT, 'default' => \XF::$time],
			'user_criteria'               => ['type' => self::JSON_ARRAY, 'default' => []],
			'category_id'                 => ['type' => self::UINT, 'required' => true],
			'code'                        => ['type' => self::JSON_ARRAY, 'default' => []],
			'item_type_id'                => ['type' => self::STR, 'required' => true],
			'thread_node_id'              => ['type' => self::UINT, 'default' => 0],
			'thread_prefix_id'            => ['type' => self::UINT, 'default' => 0],
			'discussion_thread_id'        => ['type' => self::UINT, 'default' => 0],
			'field_cache'                 => ['type' => self::JSON_ARRAY, 'default' => []],
			'item_fields'                 => ['type' => self::JSON_ARRAY, 'default' => []],
			'item_filters'                => ['type' => self::JSON_ARRAY, 'default' => []],
			'purchases'                   => ['type' => self::UINT, 'default' => 0],
			'display_in_list'             => ['type' => self::BOOL, 'default' => true],
			'is_stealth_item'             => ['type' => self::BOOL, 'default' => false],
			'auto_discard_expiry'         => ['type' => self::BOOL, 'default' => false],
			'item_flags'                  => ['type' => self::JSON_ARRAY, 'default' => []],
			'length_amount'               => ['type' => self::UINT, 'max' => 255, 'required' => true],
			'length_unit'                 => [
				'type'          => self::STR,
				'default'       => '',
				'allowedValues' => ['day', 'month', 'year', '']
			],
			'user_id'                     => [
				'type'    => self::UINT,
				'default' => 0,
				'verify'  => 'verifyUserIdOrZero'
			],
			'username'                    => ['type' => self::STR, 'maxLength' => 50],
			'ip_id'                       => ['type' => self::UINT, 'default' => 0],
			'warning_id'                  => ['type' => self::UINT, 'default' => 0],
			'warning_message'             => ['type' => self::STR, 'default' => ''],
			'moderation'                  => ['type' => self::BOOL, 'default' => false],
			'purchasable_thread_creation' => ['type' => self::BOOL, 'default' => false],
			'purchasable_thread_page'     => ['type' => self::BOOL, 'default' => false],
			'currency_id'                 => ['type' => self::UINT, 'required' => true],
			'price'                       => ['type' => self::FLOAT, 'default' => 0, 'min' => 0],
			'buyback_currency_id'         => ['type' => self::UINT, 'required' => true, 'verify' => 'verifyCurrencyId'],
			'buyback_price'               => ['type' => self::FLOAT, 'default' => 0, 'min' => 0],
			'buyback_time'                => ['type' => self::UINT, 'default' => 0],
			'buyback_replenish'           => ['type' => self::BOOL, 'default' => false],
			'notifications'               => ['type' => self::LIST_COMMA, 'default' => ''],
			'notifications_config'        => ['type' => self::LIST_COMMA, 'default' => ''],
			'stock'                       => ['type' => self::INT, 'default' => -1],
			'maxstock'                    => ['type' => self::INT, 'default' => -1],
			'refill_time'                 => ['type' => self::UINT, 'default' => 0],
			'last_refill_date'            => ['type' => self::UINT, 'default' => 0],
			'rating_count'                => ['type' => self::UINT, 'default' => 0, 'forced' => true],
			'rating_sum'                  => ['type' => self::UINT, 'default' => 0, 'forced' => true],
			'rating_avg'                  => ['type' => self::FLOAT, 'default' => 0],
			'rating_weighted'             => ['type' => self::FLOAT, 'default' => 0],
			'review_count'                => ['type' => self::UINT, 'default' => 0, 'forced' => true],
			'icon_date'                   => ['type' => self::UINT, 'default' => 0],
			'prefix_id'                   => ['type' => self::UINT, 'default' => 0],
			'tags'                        => ['type' => self::JSON_ARRAY, 'default' => []]
		];
		$structure->behaviors = [
			'XF:PermissionRebuildable' => [
				'permissionContentType' => $structure->contentType
			],
			'XF:Taggable' => ['stateField' => 'item_state'],
//			'XF:Likeable' => ['stateField' => 'item_state'],
			'XF:Indexable' => [
				'checkForUpdates' => [
					'category_id', 'description', 'prefix_id', 'tags',
					'creation_date', 'item_state'
				]
			],
			'XF:NewsFeedPublishable' => [
				'usernameField' => 'username',
				'dateField' => 'creation_date'
			],
			'XF:CustomFieldsHolder' => [
				'column' => 'item_fields',
				'valueTable' => 'xf_dbtech_shop_item_field_value',
				'checkForUpdates' => ['category_id'],
				'getAllowedFields' => function ($item): array
				{
					return $item->Category ? $item->Category->field_cache : [];
				}
			]
		];
		$structure->getters = [
			'title' => true,
			'tagline' => true,
			'duration' => true,
			'handler' => false,
			'real_review_count' => true,
			'ItemTypeTitle' => true,
			'item_fields' => true,
			'item_rating_ids' => true,
		];
		$structure->relations = [
			'MasterTagline' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'dbtech_shop_item_tag.', '$item_id']
				],
				'cascadeDelete' => true
			],
			'Permissions' => [
				'entity' => 'XF:PermissionCacheContent',
				'type' => self::TO_MANY,
				'conditions' => [
					['content_type', '=', 'dbtech_shop_item'],
					['content_id', '=', '$item_id']
				],
				'key' => 'permission_combination_id',
				'proxy' => true
			],
			'Ratings' => [
				'entity' => 'DBTech\Shop:ItemRating',
				'type' => self::TO_MANY,
				'conditions' => 'item_id',
				'key' => 'user_id'
			],
			'Category' => [
				'entity' => 'DBTech\Shop:Category',
				'type' => self::TO_ONE,
				'conditions' => 'category_id',
				'primary' => true
			],
			'Carts' => [
				'entity' => 'DBTech\Shop:Cart',
				'type' => self::TO_MANY,
				'conditions' => 'item_id',
				'with' => 'Item',
				'cascadeDelete' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'ThreadForum' => [
				'entity' => 'XF:Forum',
				'type' => self::TO_ONE,
				'conditions' => [
					['node_id', '=', '$thread_node_id']
				],
				'primary' => true,
				'with' => 'Node'
			],
			'Discussion' => [
				'entity' => 'XF:Thread',
				'type' => self::TO_ONE,
				'conditions' => [
					['thread_id', '=', '$discussion_thread_id']
				],
				'primary' => true
			],
			'Purchases' => [
				'entity' => 'DBTech\Shop:Purchase',
				'type' => self::TO_MANY,
				'conditions' => 'item_id',
				'key' => 'user_id'
			],
			'PurchaseCurrency' => [
				'entity' => 'DBTech\Shop:Currency',
				'type' => self::TO_ONE,
				'conditions' => 'currency_id',
				'primary' => true,
			],
			'BuybackCurrency' => [
				'entity' => 'DBTech\Shop:Currency',
				'type' => self::TO_ONE,
				'conditions' => [
					['currency_id', '=', '$buyback_currency_id']
				],
				'primary' => true
			],
			'Prefix' => [
				'entity' => 'DBTech\Shop:ItemPrefix',
				'type' => self::TO_ONE,
				'conditions' => 'prefix_id',
				'primary' => true
			],
			'Watch' => [
				'entity' => 'DBTech\Shop:ItemWatch',
				'type' => self::TO_MANY,
				'conditions' => 'item_id',
				'key' => 'user_id'
			],
			'DeletionLog' => [
				'entity' => 'XF:DeletionLog',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'dbtech_shop_item'],
					['content_id', '=', '$item_id']
				],
				'primary' => true
			],
			'UsergroupDiscounts' => [
				'entity' => 'DBTech\Shop:ItemUsergroupDiscount',
				'type' => self::TO_MANY,
				'conditions' => 'item_id',
				'key' => 'user_group_id'
			],
			'ApprovalQueue' => [
				'entity' => 'XF:ApprovalQueue',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'dbtech_shop_item'],
					['content_id', '=', '$item_id']
				],
				'primary' => true
			]
		];
		
		$structure->withAliases = [
			'full' => [
				'User',
				'Permissions|' . \XF::visitor()->permission_combination_id,
				function (): ?array
				{
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						return [
							'Watch|' . $userId
						];
					}
					
					return null;
				},
				function ($withParams): ?array
				{
					if (!empty($withParams['category']))
					{
						return ['Category', 'Category.Permissions|' . \XF::visitor()->permission_combination_id];
					}
					
					return null;
				},
				function ($withParams): ?array
				{
					$userId = \XF::visitor()->user_id;
					if (!empty($withParams['category']) && $userId)
					{
						return ['Category.Watch|' . $userId];
					}
					
					return null;
				},
				function (): ?string
				{
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						return 'Reactions|' . $userId;
					}
					
					return null;
				}
			],
			'fullCategory' => [
				'full',
				function (): array
				{
					$with = ['Category', 'Category.Permissions|' . \XF::visitor()->permission_combination_id];
					
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						$with[] = 'Category.Watch|' . $userId;
					}
					
					return $with;
				}
			],
			'api' => [
				'User.api',
				'Category.api',
				'Permissions|' . \XF::visitor()->permission_combination_id,
				'Category.Permissions|' . \XF::visitor()->permission_combination_id,
				function (): ?array
				{
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						return ['Watch|' . $userId];
					}
					
					return null;
				},
				function (): ?string
				{
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						return 'Reactions|' . $userId;
					}
					
					return null;
				}
			]
		];
		
		$structure->options = [
			'admin_edit' => false,
			'is_automated' => false,
			'log_moderator' => true
		];
		
		static::addBookmarkableStructureElements($structure);
		static::addReactableStructureElements($structure);
		
		return $structure;
	}

	/**
	 * Get the discount for a specific user
	 * 
	 * @param \XF\Entity\User|null $user
	 * @return array
	 */
	public function getDiscountForUser(\XF\Entity\User $user = null): array
	{
		if (!$user)
		{
			$user = \XF::visitor();
		}
		
		$bestDiscount = [
			'type' => 'percentage',
			'value' => 0,
			'usergroup_id' => 0
		];
		
		foreach ($this->UsergroupDiscounts as $discount)
		{
			if ($user->isMemberOf($discount->user_group_id))
			{
				// Find the best discount (highest percentage or highest fixed amount)
				if ($discount->discount_type === 'percentage' && $bestDiscount['type'] === 'percentage')
				{
					if ($discount->discount_value > $bestDiscount['value'])
					{
						$bestDiscount = [
							'type' => $discount->discount_type,
							'value' => $discount->discount_value,
							'usergroup_id' => $discount->user_group_id
						];
					}
				}
				elseif ($discount->discount_type === 'fixed')
				{
					if ($bestDiscount['type'] === 'percentage' || $discount->discount_value > $bestDiscount['value'])
					{
						$bestDiscount = [
							'type' => $discount->discount_type,
							'value' => $discount->discount_value,
							'usergroup_id' => $discount->user_group_id
						];
					}
				}
			}
		}
		
		return $bestDiscount;
	}

	/**
	 * Get the final price after applying usergroup discount
	 * 
	 * @param \XF\Entity\User|null $user
	 * @return float
	 */
	public function getFinalPriceForUser(\XF\Entity\User $user = null): float
	{
		$basePrice = $this->price;
		$discount = $this->getDiscountForUser($user);
		
		if ($discount['value'] <= 0)
		{
			return $basePrice;
		}
		
		if ($discount['type'] === 'percentage')
		{
			$discountAmount = ($basePrice * $discount['value']) / 100;
			return max(0, $basePrice - $discountAmount);
		}
		else // fixed
		{
			return max(0, $basePrice - $discount['value']);
		}
	}

	public function hasDiscountForUser(\XF\Entity\User $user = null): bool
	{
		$discount = $this->getDiscountForUser($user);
		return $discount['value'] > 0;
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Item|\XF\Mvc\Entity\Repository
	 */
	protected function getItemRepo()
	{
		return $this->repository('DBTech\Shop:Item');
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Purchase|\XF\Mvc\Entity\Repository
	 */
	protected function getPurchaseRepo()
	{
		return $this->repository('DBTech\Shop:Purchase');
	}

	public function getTotalPrice(): float
	{
		return $this->Item->getFinalPriceForUser() * $this->quantity;
	}

	public function getUnitPrice(): float
	{
		return $this->Item->getFinalPriceForUser();
	}
}