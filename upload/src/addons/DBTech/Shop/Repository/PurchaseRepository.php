<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Entity\TransactionLog;
use DBTech\Shop\Finder\CartFinder;
use DBTech\Shop\Finder\PurchaseFinder;
use DBTech\Shop\ItemType\AbstractHandler;
use DBTech\Shop\ItemType\ConfigurableInterface;
use XF\Db\Exception;
use XF\Entity\User;
use XF\Finder\UserFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Repository;
use XF\PreEscaped;
use XF\PrintableException;
use XF\Repository\IpRepository;
use XF\Repository\StyleRepository;
use XF\Repository\UserAlertRepository;
use XF\Service\Conversation\CreatorService;

class PurchaseRepository extends Repository
{
	/**
	 * @return CartFinder
	 */
	public function getCart(): CartFinder
	{
		return \XF::app()->finder(CartFinder::class)
			->with(['User', 'Item', 'Item.PurchaseCurrency'])
			->where('user_id', \XF::visitor()->user_id)
		;
	}

	/**
	 * @param int|null $userId
	 *
	 * @return PurchaseFinder
	 */
	public function findInventoryForUser(?int $userId = null): PurchaseFinder
	{
		return \XF::app()->finder(PurchaseFinder::class)
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
		if ($userId instanceof User)
		{
			$userId = $userId->user_id;
		}

		$cache = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $entities */
		$entities = $this->findPurchasesForUser($userId)->fetch();
		foreach ($entities AS $entity)
		{
			$entityArray = $entity->toArray(false);
			$entityArray['configuration'] = $entity->getValueSourceEncoded('configuration');

			$cache[$entity->getIdentifier()] = $entityArray;
		}

		return $cache;
	}

	/**
	 * @param User $user
	 *
	 * @return array
	 */
	public function rebuildCacheForUser(User $user): array
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
		$purchases = \XF::app()->finder(PurchaseFinder::class)
			->with('Item', true)
			->with('User', true)
			->where('Item.item_type_id', ['usernamestyle', 'usernamestyle2'])
			->fetch()
		;

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = $purchases
			->filter(function (Purchase $purchase): ?Purchase
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
		foreach ($purchases AS $purchase)
		{
			$styleProps = [];

			$handler = $purchase->handler;
			$handler->fire('username_style', [&$styleProps], $purchase->user_id);

			$data[$purchase->purchase_id] = [
				'username_css' => implode('; ', $styleProps),
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

		$styleRepo = \XF::app()->repository(StyleRepository::class);
		$styleRepo->updateAllStylesLastModifiedDate();

		return $cache;
	}

	/**
	 * @return array
	 */
	public function getUserTitleStyleCacheData(): array
	{
		$purchases = \XF::app()->finder(PurchaseFinder::class)
			->with(['Item', 'User'])
			->where('Item.item_type_id', ['usertitlestyle', 'usertitlestyle2'])
			->fetch()
		;

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = $purchases
			->filter(function (Purchase $purchase): ?Purchase
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
		foreach ($purchases AS $purchase)
		{
			$styleProps = [];

			$handler = $purchase->handler;
			$handler->fire('user_title_style', [&$styleProps], $purchase->user_id);

			$data[$purchase->purchase_id] = [
				'user_title_css' => implode('; ', $styleProps),
			];
		}

		return $data;
	}

	/**
	 * @return array
	 */
	public function getAvatarStyleCacheData(): array
	{
		$purchases = \XF::app()->finder(PurchaseFinder::class)
			->with(['Item', 'User'])
			->where('Item.item_type_id', ['avatarstyle', 'avatarstyle2'])
			->fetch()
		;

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = $purchases
			->filter(function (Purchase $purchase): ?Purchase
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
		foreach ($purchases AS $purchase)
		{
			$styleProps = [];

			$handler = $purchase->handler;
			$handler->fire('avatar_style', [&$styleProps], $purchase->user_id);

			$data[$purchase->purchase_id] = [
				'avatar_css' => implode('; ', $styleProps),
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

		$styleRepo = \XF::app()->repository(StyleRepository::class);
		$styleRepo->updateAllStylesLastModifiedDate();

		return $cache;
	}

	/**
	 * @return array
	 */
	public function rebuildAvatarStyleCache(): array
	{
		$cache = $this->getAvatarStyleCacheData();
		\XF::registry()->set('dbtShopAvatarStyle', $cache);

		$styleRepo = \XF::app()->repository(StyleRepository::class);
		$styleRepo->updateAllStylesLastModifiedDate();

		return $cache;
	}

	/**
	 * @param $userId
	 *
	 * @return PurchaseFinder
	 */
	public function findPurchasesForUser($userId): PurchaseFinder
	{
		if ($userId instanceof User)
		{
			$userId = $userId->user_id;
		}

		return \XF::app()->finder(PurchaseFinder::class)
			->with('Item', true)
			->where('user_id', $userId)
			->order('dateline', 'DESC')
		;
	}

	/**
	 * @return PurchaseFinder
	 */
	public function findExpiredPurchases(): PurchaseFinder
	{
		return \XF::app()->finder(PurchaseFinder::class)
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
		return (int) $this->db()->fetchOne("
			SELECT COUNT(purchase_id)
			FROM xf_dbtech_shop_purchase
			WHERE user_id = ?
		", $userId);
	}

	/**
	 * @return PurchaseFinder
	 */
	public function findActiveAutoBumpPurchases(): PurchaseFinder
	{
		return \XF::app()->finder(PurchaseFinder::class)
			->with('Item', true)
			->where('active', true)
			->where('Item.item_type_id', 'autobump')
		;
	}

	/**
	 * @param User $user
	 * @param bool $checkVisibility
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase>
	 */
	public function getViewablePurchasesForUser(User $user, bool $checkVisibility = true): AbstractCollection
	{
		/** @var \DBTech\Shop\XF\Entity\User $user */

		$purchases = $user->dbtech_shop_purchase;

		if ($purchases === null)
		{
			// Just in case
			return \XF::app()->em()->getEmptyCollection();
		}

		if ($checkVisibility)
		{
			$purchases = $purchases->filterViewable();
		}

		return $purchases
			->filter(function (Purchase $purchase): ?Purchase
			{
				if (!$purchase->handler->isActive())
				{
					return null;
				}

				return $purchase;
			});
	}

	/**
	 * @param User $user
	 * @param bool $checkVisibility
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase>
	 */
	public function filterActivePurchasesForUser(User $user, bool $checkVisibility = true): AbstractCollection
	{
		/** @var \DBTech\Shop\XF\Entity\User $user */

		return $this->getViewablePurchasesForUser($user, $checkVisibility)
			->filter(function (Purchase $purchase): ?Purchase
			{
				if (!$purchase->isActive())
				{
					return null;
				}

				return $purchase;
			})
		;
	}

	/**
	 * @param User $user
	 * @param bool $checkVisibility
	 *
	 * @return \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase>
	 */
	public function getValidAndDisplayedPurchasesForUser(
		User $user,
		bool $checkVisibility = true
	): AbstractCollection
	{
		/** @var \DBTech\Shop\XF\Entity\User $user */

		return $this->filterActivePurchasesForUser($user, $checkVisibility)
			->filter(function (Purchase $purchase): ?Purchase
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
	 * @throws PrintableException
	 */
	public function handleExpiredItems(): void
	{
		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = $this->findExpiredPurchases()
			->fetch()
		;
		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			if ($purchase->Item->auto_discard_expiry)
			{
				$handler->logIp(false);
				$handler->discard($null, 'auto_discard_expiry');
			}
			else if ($purchase->isActive())
			{
				$handler->deactivate();
			}
		}
	}

	/**
	 * @param Purchase $purchase
	 *
	 * @throws PrintableException
	 * @throws Exception
	 */
	public function handlePurchase(Purchase $purchase): void
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

				/** @var CurrencyRepository $currencyRepo */
				$currencyRepo = \XF::app()->repository(CurrencyRepository::class);

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
					$beneficiary = \XF::app()->em()->find(User::class, $category->beneficiary);
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
	 * @param Purchase $purchase
	 * @param string $action
	 * @param float $amount
	 * @param User $user
	 * @param User|null $recipient
	 * @param bool $logIp
	 * @param array $extraInfo
	 *
	 * @throws PrintableException
	 * @throws \LogicException
	 */
	public function logTransaction(
		Purchase $purchase,
		string   $action,
		float    $amount,
		User     $user,
		?User    $recipient = null,
		bool     $logIp = false,
		array    $extraInfo = []
	): void
	{
		$recipient = $recipient ?: $user;

		$transaction = \XF::app()->em()->create(TransactionLog::class);
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
			$ipRepo = \XF::app()->repository(IpRepository::class);
			$ipEnt = $ipRepo->logIp(
				$user->user_id,
				\XF::app()->request()->getIp(),
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
	 * @param Item $item
	 * @param Purchase $purchase
	 */
	public function sendPurchaseNotifications(Item $item, Purchase $purchase): void
	{
		if (empty($item->notifications))
		{
			return;
		}

		if (!\XF::app()->options()->dbtech_shop_purchasenotification_pm)
		{
			return;
		}

		$recipients = \XF::app()->finder(UserFinder::class)
			->where('user_id', $item->notifications)
			->fetch()
		;
		if (!$recipients)
		{
			return;
		}

		$sender = \XF::app()->em()->find(User::class, \XF::app()->options()->dbtech_shop_purchasenotification_pm);

		$params = [
			'buyer'      => $purchase->buyer_username,
			'buyer_href' => \XF::app()->router()->buildLink('full:members', (
				$purchase->buyer_user_id == $purchase->user_id
				? $purchase->User
				: ['user_id' => $purchase->buyer_user_id, 'username' => $purchase->buyer_username]
			)),

			'recipient'      => $purchase->User->username,
			'recipient_href' => \XF::app()->router()->buildLink('full:members', $purchase->User),

			'message' => $purchase->message ?: \XF::phrase('n_a'),

			'item'        => $item->title,
			'href'        => \XF::app()->router()->buildLink('full:dbtech-shop', $item),
			'tagline'     => $item->tagline,
			'description' => $item->description,
		];

		$creator = \XF::app()->service(CreatorService::class, $sender);
		$creator->setIsAutomated();
		$creator->setOptions([
			'open_invite'       => 0,
			'conversation_open' => 1,
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
	 * @param Item $item
	 * @param Purchase $purchase
	 */
	public function sendConfigurationNotifications(Item $item, Purchase $purchase): void
	{
		if (!$purchase->isConfigurable())
		{
			return;
		}

		if (empty($item->notifications_config))
		{
			return;
		}

		if (!\XF::app()->options()->dbtech_shop_confignotification_pm)
		{
			return;
		}

		$recipients = \XF::app()->finder(UserFinder::class)
			->where('user_id', $item->notifications_config)
			->fetch()
		;
		if (!$recipients)
		{
			return;
		}

		$sender = \XF::app()->em()->find(User::class, \XF::app()->options()->dbtech_shop_confignotification_pm);

		/** @var AbstractHandler|ConfigurableInterface $handler */
		$handler = $purchase->handler;

		$config = $handler->getConfigurationForConversation();

		$params = [
			'user'      => new PreEscaped($purchase->User->username),
			'user_href' => \XF::app()->router()->buildLink('full:members', $purchase->User),

			'item'        => new PreEscaped($item->title),
			'href'        => \XF::app()->router()->buildLink('full:dbtech-shop', $item),
			'tagline'     => $item->tagline,
			'description' => $item->description,

			'config' => $config,
		];

		$creator = \XF::app()->service(CreatorService::class, $sender);
		$creator->setIsAutomated();
		$creator->setOptions([
			'open_invite'       => 0,
			'conversation_open' => 1,
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
		else if (\XF::$developmentMode)
		{
			foreach ($errors AS $error)
			{
				\XF::logError($error);
			}
		}
	}

	/**
	 * @param Item $item
	 * @param Purchase $purchase
	 */
	public function sendGiftNotification(Item $item, Purchase $purchase): void
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

		/** @var AbstractHandler|ConfigurableInterface $handler */
		$handler = $purchase->handler;

		$config = $handler->getConfigurationForConversation();

		$params = [
			'user'      => new PreEscaped($purchase->Buyer->username),
			'user_href' => \XF::app()->router()->buildLink('full:members', $purchase->Buyer),

			'item'        => new PreEscaped($item->title),
			'href'        => \XF::app()->router()->buildLink('full:dbtech-shop', $item),
			'tagline'     => $item->tagline,
			'description' => $item->description,

			'message' => $purchase->message,
			'config'  => $config,
		];

		$creator = \XF::app()->service(CreatorService::class, $purchase->Buyer);
		$creator->setIsAutomated();
		$creator->setOptions([
			'open_invite'       => 0,
			'conversation_open' => 1,
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
	public function autoBumpThreads(): void
	{
		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = $this->findActiveAutoBumpPurchases()
			->fetch()
		;
		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('bump_thread');
		}
	}

	/**
	 * @param Purchase $purchase
	 * @param string $message
	 * @param array $extra
	 */
	public function sendGiftAlert(
		Purchase $purchase,
		string $message = '',
		array $extra = []
	): void
	{
		if ($purchase->Item->getFlag('send_gift_pm'))
		{
			return;
		}

		$extra = array_merge([
			'title'               => $purchase->Item->title,
			'prefix_id'           => $purchase->Item->prefix_id,
			'link'                => \XF::app()->router('public')->buildLink('nopath:dbtech-shop', $purchase->Item),
			'reason'              => $message,
			'depends_on_addon_id' => 'DBTech/Shop',
		], $extra);

		$alertRepo = \XF::app()->repository(UserAlertRepository::class);
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
}