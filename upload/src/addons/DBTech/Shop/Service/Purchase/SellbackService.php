<?php

namespace DBTech\Shop\Service\Purchase;

use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Repository\CurrencyRepository;
use XF\App;
use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\PrintableException;
use XF\Repository\ThreadRepository;
use XF\Repository\ThreadWatchRepository;
use XF\Service\AbstractService;
use XF\Service\Thread\CreatorService;
use XF\Service\ValidateAndSavableTrait;

class SellbackService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected Purchase $purchase;
	protected ?User $user = null;


	/**
	 * @param App $app
	 * @param Purchase $purchase
	 */
	public function __construct(App $app, Purchase $purchase)
	{
		parent::__construct($app);
		$this->purchase = $purchase;

		$this->setDefaults();
	}

	/**
	 *
	 */
	protected function setDefaults()
	{
	}

	/**
	 *
	 */
	protected function finalSetup()
	{
	}

	/**
	 * @return array
	 * @throws PrintableException
	 */
	protected function _validate(): array
	{
		$this->finalSetup();

		$purchase = $this->purchase;
		$errors = [];
		if (!$purchase->canSellBack())
		{
			$errors[] = \XF::phrase('dbtech_shop_cannot_sell_back_purchase_please_discard');
			return $errors;
		}

		$handler = $purchase->handler;

		$success = $handler->deactivate($error);
		if (!$success)
		{
			$errors[] = $error;
			return $errors;
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

		$purchase = $this->purchase;

		$currencyRepo = \XF::app()->repository(CurrencyRepository::class);

		$item = $purchase->Item;
		$currency = $item->BuybackCurrency;
		$category = $item->Category;

		$currencyRepo->addCurrencyAmount(
			$currency,
			'sellback',
			$item->buyback_price,
			$purchase->User,
			'dbtech_shop_item',
			$purchase->item_id,
			$purchase->purchase_id
		);

		if ($category->beneficiary_split)
		{
			// Someone is losing some amount of credits from this sellback

			// Default to item owner losing the beneficiary value and other person losing nothing
			$itemOwner = $category->beneficiary_split;
			$otherPerson = 0;

			if ($category->beneficiary)
			{
				// Other person is losing whatever the split value is, owner loses the "leftovers"
				$otherPerson = $category->beneficiary_split;
				$itemOwner = 100 - $otherPerson;
			}

			if ($itemOwner)
			{
				// Remove X amount of credits from item owner
				$currencyRepo->removeCurrencyAmount(
					$currency,
					'buyback',
					($item->buyback_price / 100) * $itemOwner,
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
					// Remove X amount of credits from other person
					$currencyRepo->removeCurrencyAmount(
						$currency,
						'buyback',
						($item->buyback_price / 100) * $otherPerson,
						$beneficiary,
						'dbtech_shop_item',
						$purchase->item_id,
						$purchase->purchase_id
					);
				}
			}
		}

		if ($item->buyback_replenish)
		{
			// Update stock and add purchase counter
			$this->db()->query("
				UPDATE xf_dbtech_shop_item
				SET stock = IF(stock > 0, stock + 1, IF(stock < 0, stock, 0))
				WHERE item_id = ?
			", $purchase->item_id);
		}

		// Decrementing purchase count / update sale count is handled in the Purchase entity so don't add it here

		//		//		$purchaseRepo = \XF::app()->repository(\DBTech\Shop\Repository\Purchase::class);
		//
		//		$purchaseRepo->sendPurchaseNotifications($item, $purchase);
		//
		//		if (
		//			$item->thread_node_id
		//			&& $item->ThreadForum
		//		)
		//		{
		//			$creator = $this->setupThreadCreation($purchase, $item->ThreadForum);
		//			if ($creator && $creator->validate())
		//			{
		//				/** @var \XF\Entity\Thread $thread */
		//				$thread = $creator->save();
		//
		//				$purchase->fastUpdate('discussion_thread_id', $thread->thread_id);
		//
		//				$creator->sendNotifications();
		//
		//				$this->afterThreadCreated($thread);
		//			}
		//		}

		$this->afterSellback();

		$db->commit();
	}

	/**
	 * @throws PrintableException
	 */
	public function afterSellback(): void
	{
		$this->purchase->delete(true, false);
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

		return \XF::asVisitor($purchase->User, function () use ($forum, $threadTitle, $threadMessage, $purchase): CreatorService
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
	 * @return mixed|null|string|string[]
	 */
	protected function getThreadMessage(Purchase $purchase): mixed
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