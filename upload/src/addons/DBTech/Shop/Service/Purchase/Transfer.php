<?php

namespace DBTech\Shop\Service\Purchase;

use DBTech\Shop\Entity\Purchase;

/**
 * Class Transfer
 *
 * @package DBTech\Shop\Service\Purchase
 */
class Transfer extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;
	
	/** @var Purchase */
	protected $purchase;
	
	/** @var \XF\Entity\User|null */
	protected $sourceUser;
	
	/** @var \XF\Entity\User|null */
	protected $toUser;
	
	/** @var bool */
	protected $isGift = false;
	
	/** @var bool */
	protected $isTrade = false;

	/** @var bool */
	protected $logIp = true;
	
	/** @var bool */
	protected $removeConfiguration = false;
	
	/** @var string */
	protected $message;
	
	
	/**
	 * Transfer constructor.
	 *
	 * @param \XF\App $app
	 * @param Purchase $purchase
	 * @param \XF\Entity\User $toUser
	 */
	public function __construct(\XF\App $app, Purchase $purchase, \XF\Entity\User $toUser)
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
	 * @return \DBTech\Shop\Service\Purchase\Transfer
	 */
	public function setIsGift(bool $isGift): Transfer
	{
		$this->isGift = $isGift;

		return $this;
	}

	/**
	 * @param bool $isTrade
	 *
	 * @return \DBTech\Shop\Service\Purchase\Transfer
	 */
	public function setIsTrade(bool $isTrade): Transfer
	{
		$this->isTrade = $isTrade;

		return $this;
	}

	/**
	 * @param bool $logIp
	 *
	 * @return \DBTech\Shop\Service\Purchase\Transfer
	 */
	public function logIp(bool $logIp): Transfer
	{
		$this->logIp = $logIp;

		return $this;
	}

	/**
	 * @param string $message
	 *
	 * @return \DBTech\Shop\Service\Purchase\Transfer
	 */
	public function setMessage(string $message): Transfer
	{
		$this->message = $message;

		return $this;
	}
	
	/**
	 * @param bool $removeConfiguration
	 *
	 * @return \DBTech\Shop\Service\Purchase\Transfer
	 */
	public function removeConfiguration(bool $removeConfiguration): Transfer
	{
		$this->removeConfiguration = $removeConfiguration;

		return $this;
	}
	
	/**
	 *
	 */
	protected function finalSetup()
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
	 * @throws \XF\PrintableException
	 */
	protected function _validate(): array
	{
		if (!$this->purchase->Item->canPurchaseForUser($this->toUser, true, $error))
		{
			$error = $error ?: \XF::phrase('dbtech_shop_cannot_gift_item_x', [
				'item' => $this->purchase->Item->title
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
	 * @throws \XF\PrintableException
	 */
	protected function _save()
	{
		$purchase = $this->purchase;
		
		$db = $this->db();
		$db->beginTransaction();
		
		$purchase->save(true, false);
		
		$this->afterTransfer();
		
		$db->commit();
		
		if ($this->isGift)
		{
			/** @var \DBTech\Shop\Repository\Purchase $purchaseRepo */
			$purchaseRepo = $this->repository('DBTech\Shop:Purchase');
			$purchaseRepo->sendGiftNotification($purchase->Item, $purchase);
			$purchaseRepo->sendGiftAlert($purchase, $this->message);
		}
	}
	
	/**
	 * @throws \XF\PrintableException
	 */
	protected function afterTransfer()
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
		elseif ($this->isTrade)
		{
			$action = 'trade';
		}
		else
		{
			$action = 'transfer';
		}
		
		$this->getPurchaseRepo()
			->logTransaction($purchase, $action, 0, $this->sourceUser, $this->toUser)
		;
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Purchase|\XF\Mvc\Entity\Repository
	 */
	protected function getPurchaseRepo()
	{
		return \XF::repository('DBTech\Shop:Purchase');
	}
}