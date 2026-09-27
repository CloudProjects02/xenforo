<?php

namespace DBTech\Shop\Service\Cart;

use DBTech\Shop\Entity\Cart;
use DBTech\Shop\Entity\Item;
use DBTech\Shop\Finder\CartFinder;
use XF\App;
use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\PrintableException;
use XF\Repository\UserRepository;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class CreatorService extends AbstractService
{
	use ValidateAndSavableTrait;

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
			->with(['Item', 'Item.PurchaseCurrency'])
			->where('user_id', $this->user->user_id)
			->fetch();
	}

	/**
	 * @param User|null $user
	 *
	 * @return $this
	 */
	public function setUser(?User $user = null): CreatorService
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
	 * @param int|string|User|null $toUser
	 *
	 * @return User
	 */
	public function getUserByInput(int|string|User|null $toUser = null): User
	{
		if ($toUser)
		{
			if (is_int($toUser))
			{
				$toUser = \XF::app()->em()->find(User::class, $toUser);
			}
			else if (is_string($toUser))
			{
				$toUser = \XF::app()->repository(UserRepository::class)
					->getUserByNameOrEmail($toUser)
				;
			}
		}

		if (!($toUser instanceof User))
		{
			$toUser = \XF::app()->repository(UserRepository::class)
				->getGuestUser('')
			;
		}

		return $toUser;
	}

	/**
	 * @param Item $item
	 * @param int $quantity
	 * @param int|string|User|null $toUser
	 * @param string $message
	 *
	 * @return $this
	 */
	public function addItem(Item $item, int $quantity = 1, int|string|User|null $toUser = null, string $message = ''): CreatorService
	{
		$cartItems = $this->cartItems;

		/** @var User $toUser */
		$toUser = $this->getUserByInput($toUser);

		// This is the $cartItems offset
		$key = $this->user->user_id . '-' . $item->item_id . '-' . $toUser->user_id;

		if (!$cartItems->offsetExists($key))
		{
			/** @var Cart $cartItem */
			$cartItem = \XF::app()->em()->create(Cart::class);
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
	 * @param Cart $cartItem
	 * @param int $quantity
	 *
	 * @return $this
	 */
	public function setQuantity(Cart $cartItem, int $quantity): CreatorService
	{
		$cartItem->quantity = $quantity;

		return $this;
	}

	/**
	 * @param Cart $cartItem
	 * @param int|string|User|null $toUser
	 * @param string $message
	 *
	 * @return $this
	 * @throws PrintableException
	 */
	public function setGiftOptions(
		Cart                 $cartItem,
		int|string|User|null $toUser = null,
		string               $message = ''
	): CreatorService
	{
		$cartItems = $this->cartItems;

		// This is the $cartItems offset
		$key = $this->user->user_id . '-' . $cartItem->item_id . '-' . $cartItem->recipient_user_id;

		/** @var User $toUser */
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
			$this->addItem($cartItem->Item);
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

			$cartItem->preSave();
			$errors = array_merge($errors, $cartItem->getErrors());
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
		foreach ($this->cartItems AS $cartItem)
		{
			$cartItem->save();
		}
	}
}