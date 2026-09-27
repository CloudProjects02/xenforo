<?php

namespace DBTech\Shop\Entity;

use DBTech\Shop\ItemType\AbstractHandler;
use DBTech\Shop\Repository\PurchaseRepository;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Entity\ViewableInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $purchase_id
 * @property int $user_id
 * @property int $buyer_user_id
 * @property string $buyer_username
 * @property int $item_id
 * @property int $dateline
 * @property array $configuration
 * @property string $message
 * @property string $message_
 * @property bool $active
 * @property bool $hidden
 * @property bool $configured
 * @property bool $gifted
 * @property bool $traded
 * @property int $expiry_date
 * @property int $discussion_thread_id
 *
 * GETTERS
 * @property-read AbstractHandler|null $handler
 *
 * RELATIONS
 * @property-read Item|null $Item
 * @property-read User|null $User
 * @property-read User|null $Buyer
 * @property-read Thread|null $Discussion
 */
class Purchase extends Entity implements ViewableInterface
{
	/**
	 * @param null $error
	 *
	 * @return mixed
	 */
	public function canView(&$error = null): bool
	{
		/*
		if (\XF::visitor()->user_id != $this->user_id
			&& !$this->isDisplayed()
		)
		{
			return false;
		}
		*/

		if (!$this->Item->canView($error))
		{
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function isLifetime(): bool
	{
		return $this->expiry_date == 0;
	}

	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->active;
	}

	/**
	 * @return bool
	 */
	public function isExpired(): bool
	{
		return ($this->expiry_date && $this->expiry_date < \XF::$time);
	}

	/**
	 * @return bool
	 */
	public function isDisplayed(): bool
	{
		return (!$this->hidden
			&& $this->Item->isDisplayed()
		);
	}

	/**
	 * @return bool
	 */
	public function canEditSettings(): bool
	{
		return (\XF::visitor()->user_id == $this->user_id
			&& !$this->isExpired()
		);
	}

	/**
	 * @return bool
	 */
	public function canDiscard(): bool
	{
		return (
			\XF::visitor()->user_id == $this->user_id
			&& $this->Item->canDiscard()
		);
	}

	/**
	 * @return bool
	 */
	public function canGift(): bool
	{
		return (\XF::visitor()->user_id == $this->user_id
			&& $this->Item->isGiftable()
			&& (!$this->gifted || $this->Item->canReGift())
		);
	}

	/**
	 * @return bool
	 */
	public function canSellBack(): bool
	{
		return (\XF::visitor()->user_id == $this->user_id
			&& $this->Item->canSellBack()
			&& (
				!$this->Item->buyback_time
				|| \XF::$time < ($this->dateline + ($this->Item->buyback_time * 3600))
			)
		);
	}

	/**
	 * @return bool
	 */
	public function canTrade(): bool
	{
		return (\XF::visitor()->user_id == $this->user_id
			&& !$this->isExpired()
			&& $this->Item->isGiftable()
			&& (!$this->traded || $this->Item->canReGift())
		);
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
	public function canConfigure(): bool
	{
		return (\XF::visitor()->user_id == $this->user_id
			&& !$this->isExpired()
			&& $this->isConfigurable()
			&& (!$this->configured || $this->Item->canReConfigure())
			&& $this->handler->getUserConfigTemplate() !== ''
		);
	}

	/**
	 * @return bool
	 */
	public function canGiftAsNew(): bool
	{
		return (
			\XF::visitor()->user_id == $this->user_id
			&& !$this->isExpired()
			&& $this->isConfigurable()
			&& $this->configured
			&& $this->Item->canReConfigure()
			&& $this->handler->canRevertConfiguration()
		);
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canUseInlineModeration(&$error = null): bool
	{
		$visitor = \XF::visitor();
		return ($visitor->user_id && $visitor->user_id == $this->user_id);
	}

	/**
	 * @return bool
	 */
	public function canSendModeratorActionAlert(): bool
	{
		$visitor = \XF::visitor();

		return (
			$visitor->user_id
			&& $visitor->user_id != $this->user_id
			//			&& $this->purchase_state == 'visible'
		);
	}

	/**
	 * @param string $key
	 *
	 * @return array|string|null
	 */
	public function getConfiguration(string $key = ''): array|string|null
	{
		return ($key ?
			($this->configuration[$key] ?? null) :
			$this->configuration
		);
	}

	/**
	 * @return AbstractHandler|null
	 * @throws \Exception
	 */
	public function getHandler(): ?AbstractHandler
	{
		$handler = $this->Item->handler;
		$handler?->setPurchase($this)
			->addListeners()
		;

		return $handler;
	}

	/**
	 *
	 */
	protected function _preSave(): void
	{
		if ($this->isUpdate())
		{
			if ($this->isExpired()
				&& $this->isChanged('active')
				&& $this->active
			)
			{
				// Somehow this was set to active, but don't trust it as the purchase is expired
				$this->active = false;
			}
		}
	}

	/**
	 *
	 */
	protected function _postSave(): void
	{
		/*
		if ($this->isUpdate())
		{
			$visibilityChange = $this->isStateChanged('active', true);
			if ($visibilityChange == 'enter' && !$this->isExpired())
			{
				$handler = $this->handler;
				$handler->activate();
			}
			else if ($visibilityChange == 'leave')
			{
				$handler = $this->handler;
				$handler->deactivate();
			}
		}
		else if ($this->isActive())
		{
			$handler = $this->handler;
			$handler->activate();
		}
		*/

		$purchaseRepo = \XF::app()->repository(PurchaseRepository::class);

		if ($this->User)
		{
			$purchaseRepo->rebuildCacheForUser($this->User);
		}

		if ($this->isUpdate()
			&& $this->isChanged('user_id')
			&& $this->Buyer
		)
		{
			// Update the buyer's cache since this was probably a gift
			$purchaseRepo->rebuildCacheForUser($this->Buyer);
		}

		if (in_array($this->Item->item_type_id, ['usernamestyle', 'usernamestyle2']))
		{
			$this->rebuildUserNameStyleCache();
		}

		if (in_array($this->Item->item_type_id, ['usertitlestyle', 'usertitlestyle2']))
		{
			$this->rebuildUserTitleStyleCache();
		}

		if (in_array($this->Item->item_type_id, ['avatarstyle', 'avatarstyle2']))
		{
			$this->rebuildAvatarStyleCache();
		}
	}

	/**
	 * @throws \XF\Db\Exception
	 */
	protected function _postDelete(): void
	{
		if ($this->User)
		{
			\XF::app()->repository(PurchaseRepository::class)
				->rebuildCacheForUser($this->User)
			;
		}

		/*
		// Update stock and add purchase counter
		$this->db()->query("
			UPDATE xf_dbtech_shop_item
				SET purchases = GREATEST(0, CAST(purchases AS SIGNED) - 1)
			WHERE item_id = ?
		", $this->item_id);
		 */

		// Decrement purchases count
		$this->db()->query('
			UPDATE xf_user
				SET dbtech_shop_purchases = GREATEST(0, CAST(dbtech_shop_purchases AS SIGNED) - 1)
			WHERE user_id = ?
		', $this->buyer_user_id);

		$category = $this->Item->Category;
		if ($category)
		{
			$category->rebuildLastSale();
			$category->saveIfChanged();
		}

		if (in_array($this->Item->item_type_id, ['usernamestyle', 'usernamestyle2']))
		{
			$this->rebuildUserNameStyleCache();
		}

		if (in_array($this->Item->item_type_id, ['usertitlestyle', 'usertitlestyle2']))
		{
			$this->rebuildUserTitleStyleCache();
		}
	}

	/**
	 *
	 */
	protected function rebuildUserNameStyleCache(): void
	{
		$repo = \XF::app()->repository(PurchaseRepository::class);

		\XF::runOnce('dbtShopUserNameStyleRebuild', function () use ($repo)
		{
			$repo->rebuildUserNameStyleCache();
		});
	}

	/**
	 *
	 */
	protected function rebuildUserTitleStyleCache(): void
	{
		$repo = \XF::app()->repository(PurchaseRepository::class);

		\XF::runOnce('dbtShopUserTitleStyleRebuild', function () use ($repo)
		{
			$repo->rebuildUserTitleStyleCache();
		});
	}

	/**
	 *
	 */
	protected function rebuildAvatarStyleCache(): void
	{
		$repo = \XF::app()->repository(PurchaseRepository::class);

		\XF::runOnce('dbtShopAvatarStyleRebuild', function () use ($repo)
		{
			$repo->rebuildAvatarStyleCache();
		});
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_purchase';
		$structure->shortName = 'DBTech\Shop:Purchase';
		$structure->primaryKey = 'purchase_id';
		$structure->columns = [
			'purchase_id'          => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id'              => ['type' => self::UINT, 'required' => true],
			'buyer_user_id'        => ['type' => self::UINT, 'required' => true],
			'buyer_username'       => ['type' => self::STR, 'maxLength' => 50],
			'item_id'              => ['type' => self::UINT, 'required' => true],
			'dateline'             => ['type' => self::UINT, 'default' => \XF::$time],
			'configuration'        => ['type' => self::JSON_ARRAY, 'default' => []],
			'message'              => ['type' => self::STR, 'default' => '', 'censor' => true],
			'active'               => ['type' => self::BOOL, 'default' => true],
			'hidden'               => ['type' => self::BOOL, 'default' => false],
			'configured'           => ['type' => self::BOOL, 'default' => false],
			'gifted'               => ['type' => self::BOOL, 'default' => false],
			'traded'               => ['type' => self::BOOL, 'default' => false],
			'expiry_date'          => ['type' => self::UINT, 'default' => 0],
			'discussion_thread_id' => ['type' => self::UINT, 'default' => 0],
		];
		$structure->getters = [
			'handler' => true,
		];
		$structure->relations = [
			'Item' => [
				'entity' => Item::class,
				'type' => self::TO_ONE,
				'conditions' => 'item_id',
				'primary' => true,
			],
			'User' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
			'Buyer' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$buyer_user_id'],
				],
				'primary' => true,
			],
			'Discussion' => [
				'entity' => Thread::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['thread_id', '=', '$discussion_thread_id'],
				],
				'primary' => true,
			],
		];

		$structure->withAliases = [
			'full' => [
				'Item',
				'Item.User',
				'Item.Permissions|' . \XF::visitor()->permission_combination_id,
				function (): ?array
				{
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						return [
							'Item.Watch|' . $userId,
						];
					}

					return null;
				},
			],
			'fullCategory' => [
				'full',
				function (): array
				{
					$with = ['Item.Category', 'Item.Category.Permissions|' . \XF::visitor()->permission_combination_id];

					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						$with[] = 'Item.Category.Watch|' . $userId;
					}

					return $with;
				},
			],
		];

		return $structure;
	}
}