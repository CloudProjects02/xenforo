<?php

namespace DBTech\Shop\Service\Cart;

use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Finder\CartFinder;
use DBTech\Shop\Repository\CurrencyRepository;
use DBTech\Shop\Repository\PurchaseRepository;
use XF\App;
use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\PrintableException;
use XF\Repository\ThreadRepository;
use XF\Repository\ThreadWatchRepository;
use XF\Service\AbstractService;
use XF\Service\Thread\CreatorService;
use XF\Service\ValidateAndSavableTrait;

class CompleteService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected array $purchases = [];
	protected ?User $user;

	/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Cart> */
	protected AbstractCollection $cartItems;


	/**
	 * @param App $app
	 */
	public function __construct(App $app)
	{
		parent::__construct($app);
		$this->setUser(\XF::visitor());

		$this->setDefaults();
	}

	/**
	 *
	 */
	protected function setDefaults(): void
	{
		$this->cartItems = \XF::app()->finder(CartFinder::class)
			->with(['User', 'Item', 'Item.PurchaseCurrency'])
			->where('user_id', $this->user->user_id)
			->fetch();
	}

	/**
	 * @param User|null $user
	 *
	 * @return $this
	 */
	public function setUser(?User $user = null): CompleteService
	{
		$this->user = $user;

		return $this;
	}

	/**
	 * @return null|User
	 */
	public function getUser(): ?User
	{
		return $this->user;
	}

	/**
	 *
	 */
	protected function finalSetup(): void
	{
		foreach ($this->cartItems AS $cartItem)
		{
			for ($i = 1; $i <= $cartItem->quantity; $i++)
			{
				$purchase = $cartItem->getNewPurchase();
				$this->purchases[] = $purchase;
			}
		}
	}

	/**
	 * @return array
	 */
	protected function _validate(): array
	{
		$this->finalSetup();

		$errors = $totalPrices = [];
		foreach ($this->cartItems AS $cartItem)
		{
			if (!$cartItem->Item
				|| !$cartItem->Item->canView()
				|| !$cartItem->Item->canPurchase()
			)
			{
				$errors[] = \XF::phrase('dbtech_shop_cannot_purchase_item_x', [
					'item' => $cartItem->Item->title,
				]);
			}

			if ($cartItem->Item->stock != -1
				&& $cartItem->quantity > $cartItem->Item->stock
			)
			{
				$errors[] = \XF::phrase('dbtech_shop_cannot_purchase_this_many_of_item_x', [
					'item' => $cartItem->Item->title,
				]);
			}

			if (!$cartItem->Item->isGiftable()
				&& $cartItem->recipient_user_id
			)
			{
				$errors[] = \XF::phrase('dbtech_shop_cannot_gift_item_x', [
					'item' => $cartItem->Item->title,
				]);
			}

			if ($cartItem->Item->isOnlyGiftable()
				&& !$cartItem->recipient_user_id)
			{
				$errors[] = \XF::phrase('dbtech_shop_must_gift_item_x', [
					'item' => $cartItem->Item->title,
				]);
			}

			if ($cartItem->recipient_user_id)
			{
				if (!$cartItem->Item->canPurchaseForUser($cartItem->Recipient, false, $error))
				{
					$errors[] = \XF::phrase('dbtech_shop_cannot_gift_item_x', [
						'item' => $cartItem->Item->title,
					]);
				}
			}
			else
			{
				if (!$cartItem->Item->canPurchaseForSelf(false, $error))
				{
					$errors[] = \XF::phrase('dbtech_shop_cannot_purchase_item_x', [
						'item' => $cartItem->Item->title,
					]);
				}
			}

			$currency = $cartItem->getCurrency();

			if (!isset($totalPrices[$currency->currency_id]))
			{
				$totalPrices[$currency->currency_id] = 0.00;
			}

			$totalPrices[$currency->currency_id] += $cartItem->getPrice();
			if ($totalPrices[$currency->currency_id] > $currency->getValueFromUser($this->user, false))
			{
				$errors[] = \XF::phrase('dbtech_shop_you_do_not_have_enough_x', [
					'currency' => $currency->title,
				]);
			}
		}

		foreach ($this->purchases AS $purchase)
		{
			$purchase->preSave();
			$errors = array_merge($errors, $purchase->getErrors());
		}

		return $errors;
	}

	/**
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	protected function _save(): void
	{
		$db = $this->db();
		$db->beginTransaction();

		$currencyRepo = \XF::app()->repository(CurrencyRepository::class);

		$purchaseRepo = \XF::app()->repository(PurchaseRepository::class);

		foreach ($this->purchases AS $purchase)
		{
			$purchase->save(true, false);

			$purchaseRepo->handlePurchase($purchase);

			$item = $purchase->Item;
			$currency = $item->PurchaseCurrency;

			$currencyRepo->removeCurrencyAmount(
				$currency,
				'purchase',
				$item->price,
				$purchase->Buyer,
				'dbtech_shop_item',
				$purchase->item_id,
				$purchase->purchase_id
			);

			$purchaseRepo->sendPurchaseNotifications($item, $purchase);

			if (
				$item->thread_node_id
				&& $item->ThreadForum
			)
			{
				$creator = $this->setupThreadCreation($purchase, $item->ThreadForum);
				if ($creator->validate())
				{
					/** @var Thread $thread */
					$thread = $creator->save();

					$purchase->fastUpdate('discussion_thread_id', $thread->thread_id);

					$creator->sendNotifications();

					$this->afterThreadCreated($thread);
				}
			}

			if ($purchase->gifted)
			{
				$purchaseRepo->sendGiftNotification($item, $purchase);
				$purchaseRepo->sendGiftAlert($purchase, $purchase->message);
			}
		}

		$this->afterComplete();

		$db->commit();
	}

	/**
	 * @throws PrintableException
	 */
	public function afterComplete(): void
	{
		foreach ($this->cartItems AS $cartItem)
		{
			$cartItem->delete(true, false);
		}
	}


	/**
	 * @param Purchase $purchase
	 * @param Forum $forum
	 *
	 * @return CreatorService
	 * @throws \Exception
	 */
	protected function setupThreadCreation(Purchase $purchase, Forum $forum): CreatorService
	{
		$threadTitle = $this->getThreadTitle($purchase);
		$threadMessage = $this->getThreadMessage($purchase);

		return \XF::asVisitor($purchase->User, function () use (
			$forum,
			$threadTitle,
			$threadMessage,
			$purchase
		): CreatorService
		{
			$creator = \XF::app()->service(CreatorService::class, $forum);
			$creator->setIsAutomated();

			$creator->setContent($threadTitle, $threadMessage, false);
			$creator->setPrefix($purchase->Item->thread_prefix_id);

			return $creator;
		});
	}

	/**
	 * @param Purchase $cartItem
	 *
	 * @return string
	 */
	protected function getThreadTitle(Purchase $cartItem): string
	{
		$item = $cartItem->Item;
		$phraseParams = [
			'title' => $item->title,
			'item_title' => $item->title,
			'tag_line' => $item->tagline,
			'username' => $item->User ? $item->User->username : $item->username,
			'item_link' => \XF::app()->router('public')->buildLink('canonical:dbtech-shop', $item),
		];

		$phrase = \XF::phrase('dbtech_shop_purchase_thread_title_create', $phraseParams);

		return $phrase->render('raw');
	}

	/**
	 * @param Purchase $purchase
	 *
	 * @return string
	 */
	protected function getThreadMessage(Purchase $purchase): string
	{
		$item = $purchase->Item;

		$phraseParams = [
			'title' => $item->title,
			'username' => $purchase->Buyer->username,
			'recipient' => $purchase->User->username,
			'message' => $purchase->message,
			'item_link' => \XF::app()->router('public')->buildLink('canonical:dbtech-shop', $item),
		];

		$phrase = \XF::phrase(
			'dbtech_shop_purchase_thread_body_create' . ($purchase->message ? '' : '_nomessage'),
			$phraseParams
		);

		return $phrase->render('raw');
	}

	/**
	 * @param Thread $thread
	 *
	 * @throws \Exception
	 */
	protected function afterThreadCreated(Thread $thread): void
	{
		\XF::asVisitor($this->user, function () use ($thread)
		{
			$threadRepo = \XF::app()->repository(ThreadRepository::class);
			$threadRepo->markThreadReadByVisitor($thread);
		});

		$threadWatchRepo = \XF::app()->repository(ThreadWatchRepository::class);
		$threadWatchRepo->autoWatchThread($thread, $this->user, true);
	}
}