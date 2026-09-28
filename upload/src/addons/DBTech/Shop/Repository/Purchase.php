<?php

namespace DBTech\Shop\Repository;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

/**
 * Class Purchase
 * @package DBTech\Shop\Repository
 */
class Purchase extends Repository
{
	/**
	 * @return Finder
	 */
	public function getCart(): Finder
	{
		return $this->finder('DBTech\Shop:Cart')
			->with(['User', 'Item', 'Item.PurchaseCurrency'])
			->where('user_id', \XF::visitor()->user_id)
			;
	}

	/**
	 * @param int|null $userId
	 *
	 * @return mixed
	 */
	public function findInventoryForUser(?int $userId = null)
	{
		return $this->finder('DBTech\Shop:Purchase')
			->with(['Buyer', 'Item'])
			->with('fullCategory')
			->where('user_id', $userId ?: \XF::visitor()->user_id)
			->where('Item.is_stealth_item', false)
			;
	}

	/**
	 * @param $userId
	 *
	 * @return array
	 */
	public function getCacheDataForUser($userId): array
	{
		if ($userId instanceof \XF\Entity\User)
		{
			$userId = $userId->user_id;
		}

		$cache = [];

		/** @var \DBTech\Shop\Entity\Purchase[] $entities */
		$entities = $this->findPurchasesForUser($userId)->fetch();
		foreach ($entities as $entity)
		{
			$entityArray = $entity->toArray(false);
			$entityArray['configuration'] = $entity->getValueSourceEncoded('configuration');

			$cache[$entity->getIdentifier()] = $entityArray;
		}

		return $cache;
	}

	/**
	 * @param \XF\Entity\User $user
	 *
	 * @return array
	 */
	public function rebuildCacheForUser(\XF\Entity\User $user): array
	{
		$cache = $this->getCacheDataForUser($user);
		$user->fastUpdate('dbtech_shop_purchase', $cache);
		return $cache;
	}

	/**
	 * @return array
	 */
	public function getUserNameStyleCacheData(): array
	{
		$purchases = $this->finder('DBTech\Shop:Purchase')
			->with('Item', true)
			->with('User', true)
			->where('Item.item_type_id', ['usernamestyle', 'usernamestyle2'])
			->fetch()
		;

		/** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
		$purchases = $purchases
			->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
			{
				$canView = \XF::asVisitor(
					$purchase->User,
					function () use ($purchase): bool
					{
						return $purchase && $purchase->canView();
					}
				);
				if (!$canView)
				{
					return null;
				}

				$purchase->clearCache('handler');
				if (!$purchase->handler->isActive())
				{
					return null;
				}

				if (!$purchase->isActive())
				{
					return null;
				}

				return $purchase;
			});

		$data = [];
		foreach ($purchases as $purchase)
		{
			$styleProps = [];

			$handler = $purchase->handler;
			$handler->fire('username_style', [&$styleProps], $purchase->user_id);

			$data[$purchase->purchase_id] = [
				'username_css' => implode('; ', $styleProps)
			];
		}

		return $data;
	}

	/**
	 * @return array
	 */
	public function rebuildUserNameStyleCache(): array
	{
		$cache = $this->getUserNameStyleCacheData();
		\XF::registry()->set('dbtShopUserNameStyle', $cache);

		/** @var \XF\Repository\Style $styleRepo */
		$styleRepo = $this->repository('XF:Style');
		$styleRepo->updateAllStylesLastModifiedDate();

		return $cache;
	}

	/**
	 * @return array
	 */
	public function getUserTitleStyleCacheData(): array
	{
		$purchases = $this->finder('DBTech\Shop:Purchase')
			->with(['Item', 'User'])
			->where('Item.item_type_id', ['usertitlestyle', 'usertitlestyle2'])
			->fetch()
		;

		/** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
		$purchases = $purchases
			->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
			{
				$canView = \XF::asVisitor(
					$purchase->User,
					function () use ($purchase): bool
					{
						return $purchase && $purchase->canView();
					}
				);
				if (!$canView)
				{
					return null;
				}

				$purchase->clearCache('handler');
				if (!$purchase->handler->isActive())
				{
					return null;
				}

				if (!$purchase->isActive())
				{
					return null;
				}

				return $purchase;
			});

		$data = [];
		foreach ($purchases as $purchase)
		{
			$styleProps = [];

			$handler = $purchase->handler;
			$handler->fire('user_title_style', [&$styleProps], $purchase->user_id);

			$data[$purchase->purchase_id] = [
				'user_title_css' => implode('; ', $styleProps)
			];
		}

		return $data;
	}

	/**
	 * @return array
	 */
	public function rebuildUserTitleStyleCache(): array
	{
		$cache = $this->getUserTitleStyleCacheData();
		\XF::registry()->set('dbtShopUserTitleStyle', $cache);

		/** @var \XF\Repository\Style $styleRepo */
		$styleRepo = $this->repository('XF:Style');
		$styleRepo->updateAllStylesLastModifiedDate();

		return $cache;
	}

	/**
	 * @param $userId
	 *
	 * @return Finder
	 */
	public function findPurchasesForUser($userId): Finder
	{
		if ($userId instanceof \XF\Entity\User)
		{
			$userId = $userId->user_id;
		}

		return $this->finder('DBTech\Shop:Purchase')
			->with('Item', true)
			->where('user_id', $userId)
			->order('dateline', 'DESC')
			;
	}

	/**
	 * @return Finder
	 */
	public function findExpiredPurchases(): Finder
	{
		return $this->finder('DBTech\Shop:Purchase')
			->with('Item', true)
			->whereOr(
				['active', true],
				['Item.auto_discard_expiry', true]
			)
			->where('expiry_date', '!=', 0)
			->where('expiry_date', '<=', \XF::$time)
			;
	}

	/**
	 * @param int $userId
	 *
	 * @return int
	 */
	public function getPurchaseCount(int $userId): int
	{
		return (int)$this->db()->fetchOne("
			SELECT COUNT(purchase_id)
			FROM xf_dbtech_shop_purchase
			WHERE user_id = ?
		", $userId);
	}

	/**
	 * @return Finder
	 */
	public function findActiveAutoBumpPurchases(): Finder
	{
		return $this->finder('DBTech\Shop:Purchase')
			->with('Item', true)
			->where('active', true)
			->where('Item.item_type_id', 'autobump')
			;
	}

	/**
	 * @param \XF\Entity\User $user
	 * @param bool $checkVisibility
	 *
	 * @return ArrayCollection
	 */
	public function getViewablePurchasesForUser(\XF\Entity\User $user, bool $checkVisibility = true): ArrayCollection
	{
		/** @var \DBTech\Shop\XF\Entity\User $user */

		$purchases = $user->dbtech_shop_purchase;

		if ($purchases === null)
		{
			// Just in case
			return $this->em->getEmptyCollection();
		}

		if ($checkVisibility)
		{
			$purchases = $purchases->filterViewable();
		}

		return $purchases
			->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
			{
				if (!$purchase->handler->isActive())
				{
					return null;
				}

				return $purchase;
			});
	}

	/**
	 * @param \XF\Entity\User $user
	 * @param bool $checkVisibility
	 *
	 * @return ArrayCollection
	 */
	public function filterActivePurchasesForUser(\XF\Entity\User $user, bool $checkVisibility = true): ArrayCollection
	{
		/** @var \DBTech\Shop\XF\Entity\User $user */

		return $this->getViewablePurchasesForUser($user, $checkVisibility)
			->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
			{
				if (!$purchase->isActive())
				{
					return null;
				}

				return $purchase;
			})
			;
	}
 
     public function filterActivePurchasesForUserCustom(\XF\Entity\User $user, bool $checkVisibility = true): ArrayCollection
    {
        /** @var \DBTech\Shop\XF\Entity\User $user */

        return $this->getViewablePurchasesForUser($user, $checkVisibility)
        ->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
        {
            return $purchase;
        });
    }

    public function getValidAndDisplayedPurchasesForUserCustom(\XF\Entity\User $user): ArrayCollection {
        /** @var \DBTech\Shop\XF\Entity\User $user */
    
        return $this->filterActivePurchasesForUserCustom($user)
            ->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
            {
                return $purchase;
            });
    }

	/**
	 * @param \XF\Entity\User $user
	 * @param bool $checkVisibility
	 *
	 * @return ArrayCollection
	 */
	public function getValidAndDisplayedPurchasesForUser(
		\XF\Entity\User $user,
		bool $checkVisibility = true
	): ArrayCollection {
		/** @var \DBTech\Shop\XF\Entity\User $user */

		return $this->filterActivePurchasesForUser($user, $checkVisibility)
			->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
			{
				if (!$purchase->isDisplayed())
				{
					return null;
				}

				return $purchase;
			})
			;
	}

	/**
	 *
	 * @throws \XF\PrintableException
	 */
	public function handleExpiredItems()
	{
		/** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
		$purchases = $this->findExpiredPurchases()
			->fetch()
		;
		foreach ($purchases as $purchase)
		{
			$handler = $purchase->handler;
			if ($purchase->Item->auto_discard_expiry)
			{
				$handler->logIp(false);
				$handler->discard($null, 'auto_discard_expiry');
			}
			elseif ($purchase->isActive())
			{
				$handler->deactivate();
			}
		}
	}

	/**
	 * @param \DBTech\Shop\Entity\Purchase $purchase
	 *
	 * @throws \XF\PrintableException
	 * @throws \XF\Db\Exception
	 */
	public function handlePurchase(\DBTech\Shop\Entity\Purchase $purchase)
	{
		$handler = $purchase->handler;
		$handler->afterPurchase();

		$item = $purchase->Item;
		$category = $item->Category;

		if ($item->price)
		{
			if ($category->beneficiary_split)
			{
				// Someone is getting some amount of credits from this sale

				// Default to item owner getting the beneficiary value and other person getting nothing
				$itemOwner = $category->beneficiary_split;
				$otherPerson = 0;

				if ($category->beneficiary)
				{
					// Other person is getting whatever the split value is, owner gets the "leftovers"
					$otherPerson = $category->beneficiary_split;
					$itemOwner = 100 - $otherPerson;
				}

				/** @var Currency $currencyRepo */
				$currencyRepo = $this->repository('DBTech\Shop:Currency');

				$currency = $item->PurchaseCurrency;
				if ($itemOwner && $item->User)
				{
					// Add X amount of credits to item owner
					$currencyRepo->addCurrencyAmount(
						$currency,
						'sale',
						($item->price / 100) * $itemOwner,
						$item->User,
						'dbtech_shop_item',
						$purchase->item_id,
						$purchase->purchase_id
					);
				}

				if ($otherPerson)
				{
					/** @var \XF\Entity\User $beneficiary */
					$beneficiary = $this->em->find('XF:User', $category->beneficiary);
					if ($beneficiary)
					{
						// Add X amount of credits to other person
						$currencyRepo->addCurrencyAmount(
							$currency,
							'sale',
							($item->price / 100) * $otherPerson,
							$beneficiary,
							'dbtech_shop_item',
							$purchase->item_id,
							$purchase->purchase_id
						);
					}
				}
			}

			$salesAmounts = $category->sales_amounts;
			if (!isset($salesAmounts[$item->currency_id]))
			{
				$salesAmounts[$item->currency_id] = 0;
			}

			$salesAmounts[$item->currency_id] += $item->price;

			$category->sales_amounts = $salesAmounts;
			$category->save(false, false);
		}

		// Update category sales count and latest sale info
		$this->db()->query("
				UPDATE xf_dbtech_shop_category
				SET sales = sales + 1,
				    latest_customer_id = ?,
				    latest_sale_id = ?
				WHERE category_id = ?
			", [$purchase->buyer_user_id, $purchase->item_id, $item->category_id])
		;

		// Update stock and add purchase counter
		$this->db()->query("
				UPDATE xf_dbtech_shop_item
				SET stock = IF(stock > 0, stock - 1, IF(stock < 0, stock, 0)),
				    purchases = purchases + 1
				WHERE item_id = ?
			", $purchase->item_id)
		;

		// Increment purchases count
		$this->db()->query('
				UPDATE xf_user
					SET dbtech_shop_purchases = dbtech_shop_purchases + 1
				WHERE user_id = ?
			', $purchase->buyer_user_id)
		;
	}

	/**
	 * @param \DBTech\Shop\Entity\Purchase $purchase
	 * @param string $action
	 * @param float $amount
	 * @param \XF\Entity\User $user
	 * @param \XF\Entity\User|null $recipient
	 * @param bool $logIp
	 * @param array $extraInfo
	 *
	 * @throws \XF\PrintableException
	 * @throws \LogicException
	 */
	public function logTransaction(
		\DBTech\Shop\Entity\Purchase $purchase,
		string $action,
		float $amount,
		\XF\Entity\User $user,
		?\XF\Entity\User $recipient = null,
		bool $logIp = false,
		array $extraInfo = []
	) {
		$recipient = $recipient ?: $user;

		$transaction = $this->em->create('DBTech\Shop:TransactionLog');
		$transaction->user_id = $user->user_id;
		$transaction->recipient_user_id = $recipient->user_id;
		$transaction->action = $action;
		$transaction->content_type = 'dbtech_shop_purchase';
		$transaction->content_id = $purchase->purchase_id;

		$transaction->info = array_merge([
			'feature'    => 'item',
			'featureid'  => $purchase->item_id,
			'currencyid' => $purchase->Item->currency_id,
			'price'      => abs($amount),
		], $extraInfo);
		$transaction->save(true, false);

		if ($logIp)
		{
			/** @var \XF\Repository\IP $ipRepo */
			$ipRepo = $this->repository('XF:Ip');
			$ipEnt = $ipRepo->logIp(
				$user->user_id,
				$this->app()->request()->getIp(),
				'dbtech_shop_transaction',
				$transaction->transaction_log_id
			);
			if ($ipEnt)
			{
				$transaction->fastUpdate('ip_id', $ipEnt->ip_id);
			}
		}
	}

	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 * @param \DBTech\Shop\Entity\Purchase $purchase
	 */
	public function sendPurchaseNotifications(\DBTech\Shop\Entity\Item $item, \DBTech\Shop\Entity\Purchase $purchase)
	{
		if (empty($item->notifications))
		{
			return;
		}

		if (!$this->options()->dbtech_shop_purchasenotification_pm)
		{
			return;
		}

		/** @var \XF\Entity\User[]|ArrayCollection $recipients */
		$recipients = $this->finder('XF:User')
			->where('user_id', $item->notifications)
			->fetch()
		;
		if (!$recipients)
		{
			return;
		}

		/** @var \XF\Entity\User $sender */
		$sender = $this->em->find('XF:User', $this->options()->dbtech_shop_purchasenotification_pm);

		$params = [
			'buyer'      => $purchase->buyer_username,
			'buyer_href' => $this->app()->router()->buildLink('full:members', (
				$purchase->buyer_user_id == $purchase->user_id
				? $purchase->User
				: ['user_id' => $purchase->buyer_user_id, 'username' => $purchase->buyer_username]
			)),

			'recipient'      => $purchase->User->username,
			'recipient_href' => $this->app()->router()->buildLink('full:members', $purchase->User),

			'message' => $purchase->message ?: \XF::phrase('n_a'),

			'item'        => $item->title,
			'href'        => $this->app()->router()->buildLink('full:dbtech-shop', $item),
			'tagline'     => $item->tagline,
			'description' => $item->description,
		];

		/** @var \XF\Service\Conversation\Creator $creator */
		$creator = $this->app()->service('XF:Conversation\Creator', $sender);
		$creator->setIsAutomated();
		$creator->setOptions([
			'open_invite'       => 0,
			'conversation_open' => 1
		]);
		$creator->setRecipientsTrusted($recipients);
		$creator->setContent(
			\XF::phrase('dbtech_shop_new_sale_x', $params),
			\XF::phrase('dbtech_shop_x_just_' . (
				$purchase->gifted
					? ('gifted' . ($purchase->message ? '_nomessage' : ''))
					: 'bought'
			) . '_y', $params)
		);

		if ($creator->validate($errors))
		{
			$creator->save();
		}
	}

	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 * @param \DBTech\Shop\Entity\Purchase $purchase
	 */
	public function sendConfigurationNotifications(\DBTech\Shop\Entity\Item $item, \DBTech\Shop\Entity\Purchase $purchase)
	{
		if (!$purchase->isConfigurable())
		{
			return;
		}

		if (empty($item->notifications_config))
		{
			return;
		}

		if (!$this->options()->dbtech_shop_confignotification_pm)
		{
			return;
		}

		/** @var \XF\Entity\User[]|ArrayCollection $recipients */
		$recipients = $this->finder('XF:User')
			->where('user_id', $item->notifications_config)
			->fetch()
		;
		if (!$recipients)
		{
			return;
		}

		/** @var \XF\Entity\User $sender */
		$sender = $this->em->find('XF:User', $this->options()->dbtech_shop_confignotification_pm);

		/** @var \DBTech\Shop\ItemType\AbstractHandler|\DBTech\Shop\ItemType\ConfigurableInterface $handler */
		$handler = $purchase->handler;

		$config = $handler->getConfigurationForConversation();

		$params = [
			'user'      => new \XF\PreEscaped($purchase->User->username),
			'user_href' => $this->app()->router()->buildLink('full:members', $purchase->User),

			'item'        => new \XF\PreEscaped($item->title),
			'href'        => $this->app()->router()->buildLink('full:dbtech-shop', $item),
			'tagline'     => $item->tagline,
			'description' => $item->description,

			'config' => $config
		];

		/** @var \XF\Service\Conversation\Creator $creator */
		$creator = $this->app()->service('XF:Conversation\Creator', $sender);
		$creator->setIsAutomated();
		$creator->setOptions([
			'open_invite'       => 0,
			'conversation_open' => 1
		]);
		$creator->setRecipientsTrusted($recipients);
		$creator->setContent(
			\XF::phrase('dbtech_shop_new_configuration_x', $params),
			\XF::phrase('dbtech_shop_x_just_configured_y' . ($config ? '' : '_noconfig'), $params)
		);

		if ($creator->validate($errors))
		{
			$creator->save();
		}
		elseif (\XF::$developmentMode)
		{
			foreach ($errors as $error)
			{
				\XF::logError($error);
			}
		}
	}

	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 * @param \DBTech\Shop\Entity\Purchase $purchase
	 */
	public function sendGiftNotification(\DBTech\Shop\Entity\Item $item, \DBTech\Shop\Entity\Purchase $purchase)
	{
		if (!$item->getFlag('send_gift_pm'))
		{
			return;
		}

		if (!$purchase->buyer_user_id || !$purchase->Buyer)
		{
			return;
		}

		if ($purchase->buyer_user_id == $purchase->user_id)
		{
			// Just in case, since we can't start conversations with ourselves
			return;
		}

		/** @var \DBTech\Shop\ItemType\AbstractHandler|\DBTech\Shop\ItemType\ConfigurableInterface $handler */
		$handler = $purchase->handler;

		$config = $handler->getConfigurationForConversation();

		$params = [
			'user'      => new \XF\PreEscaped($purchase->Buyer->username),
			'user_href' => $this->app()->router()->buildLink('full:members', $purchase->Buyer),

			'item'        => new \XF\PreEscaped($item->title),
			'href'        => $this->app()->router()->buildLink('full:dbtech-shop', $item),
			'tagline'     => $item->tagline,
			'description' => $item->description,

			'message' => $purchase->message,
			'config'  => $config
		];

		/** @var \XF\Service\Conversation\Creator $creator */
		$creator = $this->app()->service('XF:Conversation\Creator', $purchase->Buyer);
		$creator->setIsAutomated();
		$creator->setOptions([
			'open_invite'       => 0,
			'conversation_open' => 1
		]);
		$creator->setRecipientsTrusted($purchase->User);
		$creator->setContent(
			\XF::phrase('dbtech_shop_new_gift_from_x', $params),
			\XF::phrase(
				'dbtech_shop_new_gift_from_x_body' .
				($purchase->message ? '_message' : '_nomessage') .
				($config ? '_config' : '_noconfig'),
				$params
			)
		);

		if ($creator->validate($errors))
		{
			$creator->save();
		}
	}

	/**
	 *
	 */
	public function autoBumpThreads()
	{
		/** @var \DBTech\Shop\Entity\Purchase[]|ArrayCollection $purchases */
		$purchases = $this->findActiveAutoBumpPurchases()
			->fetch()
		;
		foreach ($purchases as $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('bump_thread');
		}
	}

	/**
	 * @param \DBTech\Shop\Entity\Purchase $purchase
	 * @param string $message
	 * @param array $extra
	 */
	public function sendGiftAlert(
		\DBTech\Shop\Entity\Purchase $purchase,
		string $message = '',
		array $extra = []
	) {
		if ($purchase->Item->getFlag('send_gift_pm'))
		{
			return;
		}

		$extra = array_merge([
			'title'               => $purchase->Item->title,
			'prefix_id'           => $purchase->Item->prefix_id,
			'link'                => $this->app()->router('public')->buildLink('nopath:dbtech-shop', $purchase->Item),
			'reason'              => $message,
			'depends_on_addon_id' => 'DBTech/Shop',
		], $extra);

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alert(
			$purchase->User,
			$purchase->Buyer->user_id,
			$purchase->Buyer->username,
			'dbtech_shop_item',
			$purchase->item_id,
			'gift',
			$extra
		);
	}

	/**
     * @param \XF\Entity\User $user
     * @return ArrayCollection
     */
    public function getUsernameIconPurchasesForUserPostbit(\XF\Entity\User $user, $checkVisibility = true): \XF\Mvc\Entity\ArrayCollection
    {
        return $this->filterActivePurchasesForUser($user, $checkVisibility)
            ->filter(function(\DBTech\Shop\Entity\Purchase $purchase)
            {
                /*
                 * Jace
                 */
                if (!$purchase->isShopUsernameIcon())
                {
                    return null;
                }

                return $purchase;
            });
    }
}