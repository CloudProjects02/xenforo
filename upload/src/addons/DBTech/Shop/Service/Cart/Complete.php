<?php

namespace DBTech\Shop\Service\Cart;

use DBTech\Shop\Entity\Cart;
use DBTech\Shop\Entity\Purchase;

/**
 * Class Complete
 *
 * @package DBTech\Shop\Service\Cart
 */
class Complete extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;
	
	/** @var \XF\Mvc\Entity\ArrayCollection|Cart[] */
	protected $cartItems;
	
	/** @var Purchase[]  */
	protected $purchases = [];
	
	/** @var \XF\Entity\User|null */
	protected $user;
	
	
	/**
	 * Complete constructor.
	 *
	 * @param \XF\App $app
	 */
	public function __construct(\XF\App $app)
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
		$this->cartItems = $this->finder('DBTech\Shop:Cart')
			->with(['User', 'Item', 'Item.PurchaseCurrency'])
			->where('user_id', $this->user->user_id)
			->fetch();
	}

	/**
	 * @param \XF\Entity\User|null $user
	 *
	 * @return $this
	 */
	public function setUser(?\XF\Entity\User $user = null): Complete
	{
		$this->user = $user;

		return $this;
	}
	
	/**
	 * @return null|\XF\Entity\User
	 */
	public function getUser(): ?\XF\Entity\User
	{
		return $this->user;
	}
	
	/**
	 *
	 */
	protected function finalSetup(): void
	{
		foreach ($this->cartItems as $cartItem)
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
		foreach ($this->cartItems as $cartItem)
		{
			$error = null;
			
			if (!$cartItem->Item
				|| !$cartItem->Item->canView()
				|| !$cartItem->Item->canPurchase()
			) {
				$errors[] = $error ?: \XF::phrase('dbtech_shop_cannot_purchase_item_x', [
					'item' => $cartItem->Item->title
				]);
				
				$error = null;
			}
			
			if ($cartItem->Item->stock != -1
				&& $cartItem->quantity > $cartItem->Item->stock
			) {
				$errors[] = \XF::phrase('dbtech_shop_cannot_purchase_this_many_of_item_x', [
					'item' => $cartItem->Item->title
				]);
			}
			
			if (!$cartItem->Item->isGiftable()
				&& $cartItem->recipient_user_id
			) {
				$errors[] = \XF::phrase('dbtech_shop_cannot_gift_item_x', [
					'item' => $cartItem->Item->title
				]);
			}
			
			if ($cartItem->Item->isOnlyGiftable()
				&& !$cartItem->recipient_user_id)
			{
				$errors[] = \XF::phrase('dbtech_shop_must_gift_item_x', [
					'item' => $cartItem->Item->title
				]);
			}
			
			if ($cartItem->recipient_user_id)
			{
				if (!$cartItem->Item->canPurchaseForUser($cartItem->Recipient, false, $error))
				{
					$errors[] = $error ?: \XF::phrase('dbtech_shop_cannot_gift_item_x', [
						'item' => $cartItem->Item->title
					]);
					
					$error = null;
				}
			}
			else
			{
				if (!$cartItem->Item->canPurchaseForSelf(false, $error))
				{
					$errors[] = $error ?: \XF::phrase('dbtech_shop_cannot_purchase_item_x', [
						'item' => $cartItem->Item->title
					]);
					
					$error = null;
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
					'currency' => $currency->title
				]);
			}
		}
		
		foreach ($this->purchases as $purchase)
		{
			$purchase->preSave();
			$errors = array_merge($errors, $purchase->getErrors());
		}
		
		return $errors;
	}
	
	/**
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	protected function _save()
	{
		$db = $this->db();
		$db->beginTransaction();
		
		/** @var \DBTech\Shop\Repository\Currency $currencyRepo */
		$currencyRepo = $this->repository('DBTech\Shop:Currency');
		
		/** @var \DBTech\Shop\Repository\Purchase $purchaseRepo */
		$purchaseRepo = $this->repository('DBTech\Shop:Purchase');
		
		foreach ($this->purchases as $purchase)
		{
			$purchase->save(true, false);
			
			$purchaseRepo->handlePurchase($purchase);
			
			$item = $purchase->Item;
			$currency = $item->PurchaseCurrency;
			
			// Use the discounted price for the actual transaction
			$finalPrice = $item->getFinalPriceForUser($purchase->Buyer);
			
			$currencyRepo->removeCurrencyAmount(
				$currency,
				'purchase',
				$finalPrice, // Use discounted price instead of base price
				$purchase->Buyer,
				'dbtech_shop_item',
				$purchase->item_id,
				$purchase->purchase_id
			);
			
			$purchaseRepo->sendPurchaseNotifications($item, $purchase);
			
			if (
				$item->thread_node_id
				&& $item->ThreadForum
			) {
				$creator = $this->setupThreadCreation($purchase, $item->ThreadForum);
				if ($creator && $creator->validate())
				{
					/** @var \XF\Entity\Thread $thread */
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
	 * @throws \XF\PrintableException
	 */
	public function afterComplete()
	{
		foreach ($this->cartItems as $cartItem)
		{
			$cartItem->delete(true, false);
		}
	}
	
	
	/**
	 * @param Purchase $purchase
	 * @param \XF\Entity\Forum $forum
	 *
	 * @return \XF\Service\Thread\Creator
	 * @throws \Exception
	 */
	protected function setupThreadCreation(Purchase $purchase, \XF\Entity\Forum $forum): \XF\Service\Thread\Creator
	{
		$threadTitle = $this->getThreadTitle($purchase);
		$threadMessage = $this->getThreadMessage($purchase);
		
		return \XF::asVisitor($purchase->User, function () use (
			$forum,
			$threadTitle,
			$threadMessage,
			$purchase
		): \XF\Service\Thread\Creator {
			/** @var \XF\Service\Thread\Creator $creator */
			$creator = $this->service('XF:Thread\Creator', $forum);
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
			'item_link' => $this->app->router('public')->buildLink('canonical:dbtech-shop', $item),
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
			'item_link' => $this->app->router('public')->buildLink('canonical:dbtech-shop', $item),
		];
		
		$phrase = \XF::phrase(
			'dbtech_shop_purchase_thread_body_create' . ($purchase->message ? '' : '_nomessage'),
			$phraseParams
		);
		
		return $phrase->render('raw');
	}
	
	/**
	 * @param \XF\Entity\Thread $thread
	 *
	 * @throws \Exception
	 */
	protected function afterThreadCreated(\XF\Entity\Thread $thread)
	{
		\XF::asVisitor($this->user, function () use ($thread)
		{
			/** @var \XF\Repository\Thread $threadRepo */
			$threadRepo = $this->repository('XF:Thread');
			$threadRepo->markThreadReadByVisitor($thread);
		});
		
		/** @var \XF\Repository\ThreadWatch $threadWatchRepo */
		$threadWatchRepo = $this->repository('XF:ThreadWatch');
		$threadWatchRepo->autoWatchThread($thread, $this->user, true);
	}
}