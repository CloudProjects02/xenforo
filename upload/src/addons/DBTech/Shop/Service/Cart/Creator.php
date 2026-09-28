<?php

namespace DBTech\Shop\Service\Cart;

use DBTech\Shop\Entity\Cart;
use DBTech\Shop\Entity\Item;

/**
 * Class Creator
 *
 * @package DBTech\Shop\Service\Cart
 */
class Creator extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/** @var \XF\Mvc\Entity\ArrayCollection|Cart[] */
	protected $cartItems;

	/** @var \XF\Entity\User|null */
	protected $user;
	
	
	/**
	 * Creator constructor.
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
			->with(['Item', 'Item.PurchaseCurrency'])
			->where('user_id', $this->user->user_id)
			->fetch();
	}

	/**
	 * @param \XF\Entity\User|null $user
	 *
	 * @return $this
	 */
	public function setUser(?\XF\Entity\User $user = null): Creator
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
	 * @param null $toUser
	 *
	 * @return \XF\Entity\User
	 */
	public function getUserByInput($toUser = null): \XF\Entity\User
	{
		if ($toUser)
		{
			if (is_int($toUser))
			{
				$toUser = $this->em()->find('XF:User', $toUser);
			}
			elseif (is_string($toUser))
			{
				/** @var \XF\Entity\User $toUser */
				$toUser = $this->repository('XF:User')->getUserByNameOrEmail($toUser);
			}
		}

		if (!($toUser instanceof \XF\Entity\User))
		{
			/** @var \XF\Entity\User $toUser */
			$toUser = $this->repository('XF:User')->getGuestUser('');
		}

		return $toUser;
	}

	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 * @param int $quantity
	 * @param \XF\Entity\User|int|string|null $toUser
	 * @param string $message
	 *
	 * @return $this
	 */
	public function addItem(Item $item, int $quantity = 1, $toUser = null, string $message = ''): Creator
	{
		$cartItems = $this->cartItems;

		/** @var \XF\Entity\User $toUser */
		$toUser = $this->getUserByInput($toUser);

		// This is the $cartItems offset
		$key = $this->user->user_id . '-' . $item->item_id . '-' . $toUser->user_id;

		if (!$cartItems->offsetExists($key))
		{
			/** @var Cart $cartItem */
			$cartItem = $this->em()->create('DBTech\Shop:Cart');
			$cartItem->item_id = $item->item_id;
			$cartItem->user_id = $this->user->user_id;
			$cartItem->quantity = 0; // Default to 0 since we want to add the passed quantity

			$cartItems->offsetSet($key, $cartItem);
		}
		else
		{
			/** @var Cart $cartItem */
			$cartItem = $cartItems->offsetGet($key);
		}
		
		if ($toUser->user_id)
		{
			$cartItem->recipient_user_id = $toUser->user_id;
			$cartItem->recipient_username = $toUser->username;
			$cartItem->message = $message;

			$cartItem->hydrateRelation('Recipient', $toUser);
		}
		else
		{
			$cartItem->recipient_user_id = 0;
			$cartItem->recipient_username = '';
			$cartItem->message = '';

			$cartItem->hydrateRelation('Recipient', null);
		}
		
		if ($item->isUnique() || $item->isExclusive())
		{
			// Unique items can only ever have 1 in the quantity, so enforce that here
			$cartItem->quantity = 1;
		}
		else
		{
			$cartItem->quantity += $quantity;
		}

		return $this;
	}

	/**
	 * @param \DBTech\Shop\Entity\Cart $cartItem
	 * @param int $quantity
	 *
	 * @return $this
	 */
	public function setQuantity(Cart $cartItem, int $quantity): Creator
	{
		$cartItem->quantity = $quantity;

		return $this;
	}

	/**
	 * @param Cart $cartItem
	 * @param \XF\Entity\User|int|string|null $toUser
	 * @param string $message
	 *
	 * @return $this
	 * @throws \XF\PrintableException
	 */
	public function setGiftOptions(Cart $cartItem, $toUser = null, string $message = ''): Creator
	{
		$cartItems = $this->cartItems;

		// This is the $cartItems offset
		$key = $this->user->user_id . '-' . $cartItem->item_id . '-' . $cartItem->recipient_user_id;

		/** @var \XF\Entity\User $toUser */
		$toUser = $this->getUserByInput($toUser);

		if ($toUser->user_id && $cartItem->Item->isGiftable())
		{
			if ($toUser->user_id != $cartItem->recipient_user_id)
			{
				if ($cartItem->quantity > 1)
				{
					$cartItem->quantity -= 1;
				}
				else
				{
					// Delete the existing cart item and add it fresh
					$cartItem->delete();
					$cartItems->offsetUnset($key);
				}

				// We're changing a cart item's gift options
				$this->addItem($cartItem->Item, 1, $toUser, $message);
			}
		}
		else
		{
			if ($cartItem->quantity > 1)
			{
				$cartItem->quantity -= 1;
			}
			else
			{
				// Delete the existing cart item and add it fresh
				$cartItem->delete();
				$cartItems->offsetUnset($key);
			}

			// Fallback to adding the item, since this will handle merging / checking for unique
			$this->addItem($cartItem->Item, 1);
		}

		return $this;
	}
	
	/**
	 *
	 */
	protected function finalSetup(): void
	{
	}

	/**
	 * @return array
	 */
	protected function _validate(): array
	{
		$this->finalSetup();

		$errors = [];
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

			$cartItem->preSave();
			$errors = array_merge($errors, $cartItem->getErrors());
		}

		return $errors;
	}
	
	/**
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	protected function _save(): void
	{
		foreach ($this->cartItems as $cartItem)
		{
			$cartItem->save();
		}
	}
}