<?php

namespace DBTech\Shop\Service\Purchase;

use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Repository\PurchaseRepository;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class TransferService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected Purchase $purchase;
	protected User $sourceUser;
	protected User $toUser;
	protected bool $isGift = false;
	protected bool $isTrade = false;
	protected bool $logIp = true;
	protected bool $removeConfiguration = false;
	protected ?string $message = null;


	/**
	 * @param App $app
	 * @param Purchase $purchase
	 * @param User $toUser
	 */
	public function __construct(App $app, Purchase $purchase, User $toUser)
	{
		parent::__construct($app);
		$this->purchase = $purchase;
		$this->sourceUser = $purchase->User;
		$this->toUser = $toUser;

		$this->setDefaults();
	}

	/**
	 *
	 */
	protected function setDefaults()
	{
	}

	/**
	 * @param bool $isGift
	 *
	 * @return TransferService
	 */
	public function setIsGift(bool $isGift): TransferService
	{
		$this->isGift = $isGift;

		return $this;
	}

	/**
	 * @param bool $isTrade
	 *
	 * @return TransferService
	 */
	public function setIsTrade(bool $isTrade): TransferService
	{
		$this->isTrade = $isTrade;

		return $this;
	}

	/**
	 * @param bool $logIp
	 *
	 * @return TransferService
	 */
	public function logIp(bool $logIp): TransferService
	{
		$this->logIp = $logIp;

		return $this;
	}

	/**
	 * @param string $message
	 *
	 * @return TransferService
	 */
	public function setMessage(string $message): TransferService
	{
		$this->message = $message;

		return $this;
	}

	/**
	 * @param bool $removeConfiguration
	 *
	 * @return TransferService
	 */
	public function removeConfiguration(bool $removeConfiguration): TransferService
	{
		$this->removeConfiguration = $removeConfiguration;

		return $this;
	}

	/**
	 *
	 */
	protected function finalSetup(): void
	{
		$purchase = $this->purchase;
		$oldOwner = $purchase->User;
		$newOwner = $this->toUser;

		if ($this->removeConfiguration)
		{
			$purchase->configured = false;
			$purchase->configuration = [];
		}

		$purchase->user_id = $newOwner->user_id;
		$purchase->hydrateRelation('User', $newOwner);

		if ($this->isGift)
		{
			$purchase->gifted = true;
		}

		if ($this->isTrade)
		{
			$purchase->traded = true;
		}

		if ($this->isGift || $this->isTrade)
		{
			if ($this->message !== null)
			{
				$purchase->message = $this->message;
			}

			$purchase->buyer_user_id = $oldOwner->user_id;
			$purchase->buyer_username = $oldOwner->username;
			$purchase->hydrateRelation('Buyer', $oldOwner);
		}
	}

	/**
	 * @return array
	 * @throws PrintableException
	 */
	protected function _validate(): array
	{
		if (!$this->purchase->Item->canPurchaseForUser($this->toUser, true, $error))
		{
			$error = $error ?: \XF::phrase('dbtech_shop_cannot_gift_item_x', [
				'item' => $this->purchase->Item->title,
			]);

			return is_array($error) ? $error : [$error];
		}

		// Deactivate purchase - important this runs before finalSetup
		$success = $this->purchase->handler->deactivate($error);
		if (!$success)
		{
			return is_array($error) ? $error : [$error];
		}

		$this->finalSetup();

		$this->purchase->preSave();
		return $this->purchase->getErrors();
	}

	/**
	 * @throws PrintableException
	 */
	protected function _save(): void
	{
		$purchase = $this->purchase;

		$db = $this->db();
		$db->beginTransaction();

		$purchase->save(true, false);

		$this->afterTransfer();

		$db->commit();

		if ($this->isGift)
		{
			$purchaseRepo = \XF::app()->repository(PurchaseRepository::class);
			$purchaseRepo->sendGiftNotification($purchase->Item, $purchase);
			$purchaseRepo->sendGiftAlert($purchase, $this->message);
		}
	}

	/**
	 * @throws PrintableException
	 */
	protected function afterTransfer(): void
	{
		$purchase = $this->purchase;

		if (!$this->removeConfiguration && $purchase->configured)
		{
			// Re-activate configured purchase which should now be for the new user
			$this->purchase->handler->activate();
		}

		if ($this->isGift)
		{
			$action = 'gift';
		}
		else if ($this->isTrade)
		{
			$action = 'trade';
		}
		else
		{
			$action = 'transfer';
		}

		\XF::app()->repository(PurchaseRepository::class)
			->logTransaction(
				$purchase,
				$action,
				0,
				$this->sourceUser,
				$this->toUser
			)
		;
	}
}